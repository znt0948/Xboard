#!/bin/bash
set -e

# 切换到脚本所在目录
cd "$(dirname "$0")"

# 检查 Git 环境
if [ ! -d ".git" ]; then
  echo "❌ Not a Git repository. Please deploy via Git clone."
  exit 1
fi

if ! command -v git &>/dev/null; then
  echo "❌ Git not installed. Please install Git."
  exit 1
fi

# 确保安全目录
git config --global --add safe.directory "$(pwd)"

# 获取当前分支并更新
branch=$(git rev-parse --abbrev-ref HEAD)
echo "🚀 Updating current branch: $branch ..."
git fetch origin "$branch"
git reset --hard "origin/$branch"

# 更新 Composer
echo "📦 Updating dependencies..."
rm -f composer.phar
wget -q https://getcomposer.org/download/latest-stable/composer.phar -O composer.phar
php composer.phar install --no-dev --optimize-autoloader

# 数据库迁移 & 系统更新
echo "🧩 Running migrations and XBoard update..."
php artisan migrate --force || echo "⚠️ Migration failed (non-critical)"
php artisan xboard:update || echo "⚠️ XBoard update command failed"

# 权限修复
if [ -f "/etc/init.d/bt" ] || [ -f "/.dockerenv" ]; then
  chown -R www:www "$(pwd)"
fi

if [ -d ".docker/.data" ]; then
  chmod -R 755 .docker/.data
fi

echo "✅ Update completed successfully!"