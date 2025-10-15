#!/bin/bash

if [ ! -d ".git" ]; then
  echo "Please deploy using Git."
  exit 1
fi

if ! command -v git &> /dev/null; then
    echo "Git is not installed! Please install git and try again."
    exit 1
fi

# 确保安全目录
git config --global --add safe.directory $(pwd)

# 强制覆盖本地 master
git fetch origin
git reset --hard origin/master
git clean -fdx

# XBoard 系统更新
php artisan xboard:update

# 权限修复
if [ -f "/etc/init.d/bt" ] || [ -f "/.dockerenv" ]; then
  chown -R www:www $(pwd)
fi

if [ -d ".docker/.data" ]; then
  chmod -R 777 .docker/.data
fi