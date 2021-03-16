### 安装composer

线上执行 composer install --no-dev

本地执行 composer install

#### JWT 依赖安装
composer require tymon/jwt-auth 1.*@rc

php artisan jwt:secret  


#### 其他依赖
composer require doctrine/dbal


#### 初始数据填充
```
php artisan migrate:refresh

php artisan db:seed --class=InitSeeder

```

#### 队列使用
  1. 先在.env中配置
```
 QUEUE_CONNECTION=redis
```

   2. 然后执行队列监听
```
php artisan queue:work >> /data/logs/queue/crm-queue.log &

```

