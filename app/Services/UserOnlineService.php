<?php


namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserOnlineService
{
    /**
     * 不参与统计的IP
     */
    private const EXCLUDED_IPS = [];

    /**
     * 缓存相关常量
     */
    private const CACHE_PREFIX = 'ALIVE_IP_USER_';

    /**
     * 获取所有限制设备用户的在线数量
     */
    public function getAliveList(Collection $deviceLimitUsers): array
{
    if ($deviceLimitUsers->isEmpty()) {
        return [];
    }

    $cacheKeys = $deviceLimitUsers->pluck('id')
        ->map(fn(int $id): string => self::CACHE_PREFIX . $id)
        ->all();

    $cached = collect(cache()->many($cacheKeys))
        ->filter(); // remove nulls

    // 每个缓存项是一个数组（按节点分组），我们使用 calculateDeviceCount 计算排除 EXCLUDED_IPS 后的设备数
    return $cached
        ->map(fn(array $data): int => self::calculateDeviceCount($data))
        ->filter(fn(int $count): bool => $count > 0)
        ->mapWithKeys(fn(int $count, string $key): array => [
            (int) Str::after($key, self::CACHE_PREFIX) => $count
        ])
        ->all();
}

/**
 * 获取指定用户的在线设备信息
 */
public static function getUserDevices(int $userId): array
{
    $data = cache()->get(self::CACHE_PREFIX . $userId, []);
    if (empty($data)) {
        return ['total_count' => 0, 'devices' => []];
    }

    // 逐节点收集设备信息，排除 EXCLUDED_IPS 中的真实 IP（去掉 node suffix）
    $devices = collect($data)
        ->filter(fn(mixed $item): bool => is_array($item) && isset($item['aliveips']))
        ->flatMap(function (array $nodeData, string $nodeKey): array {
            return collect($nodeData['aliveips'])
                ->reject(fn(string $ipNodeId): bool => in_array(\Illuminate\Support\Str::before($ipNodeId, '_'), self::EXCLUDED_IPS))
                ->mapWithKeys(function (string $ipNodeId) use ($nodeData, $nodeKey): array {
                    $ip = \Illuminate\Support\Str::before($ipNodeId, '_');
                    return [
                        $ip => [
                            'ip' => $ip,
                            'last_seen' => $nodeData['lastupdateAt'] ?? null,
                            'node_type' => Str::before($nodeKey, (string) ($nodeData['lastupdateAt'] ?? ''))
                        ]
                    ];
                })
                ->all();
        })
        ->values()
        ->all();

    // 计算排除名单后的总数（与 getOnlineCount 的逻辑一致）
    $filteredCount = self::calculateDeviceCount($data);

    return [
        'total_count' => $filteredCount,
        'devices' => $devices
    ];
}


    /**
     * 批量获取用户在线设备数
     */
    public function getOnlineCounts(array $userIds): array
    {
        $cacheKeys = collect($userIds)
            ->map(fn(int $id): string => self::CACHE_PREFIX . $id)
            ->all();

        return collect(cache()->many($cacheKeys))
            ->filter()
            ->map(function (array $data): int {
                if (empty($data)) {
                    return 0;
                }
                $filtered = collect($data)
                    ->filter(fn($item) => is_array($item) && isset($item['aliveips']))
                    ->flatMap(fn($node) => $node['aliveips'])
                    ->reject(fn(string $ipNodeId): bool => in_array(\Illuminate\Support\Str::before($ipNodeId, '_'), self::EXCLUDED_IPS))
                    ->unique()
                    ->values()
                    ->all();
                return count($filtered);
            })
            ->all();
    }

    /**
     * 获取用户在线设备数
     */
    public function getOnlineCount(int $userId): int
    {
        $data = cache()->get(self::CACHE_PREFIX . $userId, []);
        if (empty($data)) {
            return 0;
        }
        $filtered = collect($data)
            ->filter(fn($item) => is_array($item) && isset($item['aliveips']))
            ->flatMap(fn($node) => $node['aliveips'])
            ->reject(fn(string $ipNodeId): bool => in_array(\Illuminate\Support\Str::before($ipNodeId, '_'), self::EXCLUDED_IPS))
            ->unique()
            ->values()
            ->all();
        return count($filtered);
    }

    /**
     * 计算在线设备数量
     */
    public static function calculateDeviceCount(array $ipsArray): int
    {
        $mode = (int) admin_setting('device_limit_mode', 0);

        return match ($mode) {
            1 => collect($ipsArray)
                ->filter(fn(mixed $data): bool => is_array($data) && isset($data['aliveips']))
                ->flatMap(
                    fn(array $data): array => collect($data['aliveips'])
                        ->map(fn(string $ipNodeId): string => \Illuminate\Support\Str::before($ipNodeId, '_'))
                        ->reject(fn(string $ip): bool => in_array($ip, self::EXCLUDED_IPS))
                        ->unique()
                        ->all()
                )
                ->unique()
                ->count(),
            0 => collect($ipsArray)
                ->filter(fn(mixed $data): bool => is_array($data) && isset($data['aliveips']))
                ->sum(fn(array $data): int =>
                    collect($data['aliveips'])
                        ->reject(fn(string $ipNodeId): bool => in_array(\Illuminate\Support\Str::before($ipNodeId, '_'), self::EXCLUDED_IPS))
                        ->count()
                ),
            default => throw new \InvalidArgumentException("Invalid device limit mode: $mode"),
        };
    }
}