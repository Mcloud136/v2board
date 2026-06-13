# Laravel 12 → 13 Upgrade Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade V2Board from Laravel 12.62.0 to Laravel 13.15.0, adopt all optional new features, improve performance and reliability.

**Architecture:** In-place upgrade on master branch — composer update, fix breaking changes layer by layer, adopt new features incrementally. Commit after each Task for rollback capability.

**Tech Stack:** PHP 8.5.7, Laravel 13.x, Symfony 8.x, MySQL 8.0, Valkey (Redis), Horizon 5.x

---

## Dependency & Impact Analysis

```
Task 1 (Backup)
  └─> Task 2 (Composer Upgrade) — prerequisite for all subsequent tasks
        ├─> Task 3 (Breaking Changes Fix) — blocks runtime
        │     ├─> 3a: PDO Fetch modes
        │     ├─> 3b: Arr:: compatibility
        │     └─> 3c: Blueprint datetime
        ├─> Task 4 (New Feature Adoption) — optional optimizations
        │     ├─> 4a: Cache::touch()
        │     ├─> 4b: Bus::bulk()
        │     ├─> 4c: MySQL STRAIGHT JOIN
        │     ├─> 4d: eventStream()
        │     └─> 4e: Plural morph pivot
        ├─> Task 5 (Horizon/Queue Optimization) — depends on Task 3
        └─> Task 6 (Verify & Deploy) — depends on all prior tasks
```

**Key findings from codebase scan:**
- No Model boot() customization — "no new instances during boot" change has NO impact
- No route prefixes — hyphenated prefix change has NO impact
- No subdomain routes — subdomain priority change has NO impact
- No custom pagination views — pagination view rename has NO impact
- Only 1 Arr:: usage (Handler.php:70) — minimal impact
- 0 Str:: usage — no impact
- Standard Job pattern with traits — compatible

---

## Task 1: Backup & Preparation

**Files:** No code changes

- [ ] **Step 1: Backup database**

```bash
cd /www/wwwroot/wxmuma.cn
mysqldump -h mysql3.sqlpub.com -P 3308 -u payment -p'PASSWORD' payment > /tmp/v2board_backup_$(date +%Y%m%d).sql
```

- [ ] **Step 2: Backup project directory**

```bash
cp -r /www/wwwroot/wxmuma.cn /www/wwwroot/wxmuma.cn.bak.$(date +%Y%m%d)
```

- [ ] **Step 3: Backup .env**

```bash
cp /www/wwwroot/wxmuma.cn/.env /tmp/.env.backup
```

- [ ] **Step 4: Record current version**

```bash
cd /www/wwwroot/wxmuma.cn
git log --oneline -1 > /tmp/pre-upgrade-commit.txt
/www/server/php/85/bin/php artisan --version >> /tmp/pre-upgrade-commit.txt
cat /tmp/pre-upgrade-commit.txt
```

---

## Task 2: Composer Upgrade Laravel 12 → 13

**Files:**
- Modify: `composer.json` line 30: `"laravel/framework": "^12.0"` → `"^13.0"`

- [ ] **Step 1: Modify composer.json version constraint**

```bash
cd /www/wwwroot/wxmuma.cn
sed -i 's/"laravel\/framework": "\^12.0"/"laravel\/framework": "^13.0"/' composer.json
grep "laravel/framework" composer.json
# Expected: "laravel/framework": "^13.0",
```

- [ ] **Step 2: Run composer update**

```bash
cd /www/wwwroot/wxmuma.cn
/www/server/php/85/bin/php composer.phar update laravel/framework --with-all-dependencies --no-dev --optimize-autoloader 2>&1
```

**Expected result:**
- laravel/framework upgrades to v13.x
- All symfony/* components upgrade from 7.4.x to 8.x
- brick/math may upgrade
- No errors

- [ ] **Step 3: Verify installation**

```bash
/www/server/php/85/bin/php artisan --version
# Expected: Laravel Framework 13.x.x
```

- [ ] **Step 4: Check deprecation warnings**

```bash
/www/server/php/85/bin/php artisan route:list 2>&1 | grep -i "deprecated\|error"
# Expected: no output
```

- [ ] **Step 5: Commit**

```bash
cd /www/wwwroot/wxmuma.cn
git add composer.json composer.lock
git commit -m "upgrade: Laravel 12 -> 13 with Symfony 8 components"
```

---

## Task 3: Breaking Changes Fix

### Task 3a: PDO Fetch Modes

**Impact:** Laravel 13 changes PDO fetch mode defaults. Query result array key names may change.

**Files:**
- Check: all files using `->toArray()`, `->pluck()`, `DB::select()` in `app/`

- [ ] **Step 1: Scan affected code**

```bash
cd /www/wwwroot/wxmuma.cn
grep -rn "DB::select\|DB::table\|->toArray\|->pluck" app/ --include="*.php" | wc -l
```

- [ ] **Step 2: Run existing tests**

```bash
/www/server/php/85/bin/php artisan test 2>&1
# If tests fail, fix each one
```

- [ ] **Step 3: Manually verify key queries**

```bash
/www/server/php/85/bin/php artisan tinker --execute="
use App\Models\Order;
\$o = Order::first();
echo json_encode(\$o->toArray());
"
# Expected: normal JSON output with correct key names
```

- [ ] **Step 4: Commit fixes if any**

---

### Task 3b: Arr:: Compatibility Check

**Impact:** Project has only 1 Arr:: usage at `app/Exceptions/Handler.php:70`: `Arr::except($trace, ['args'])`

**Files:**
- Check: `app/Exceptions/Handler.php:70`

- [ ] **Step 1: Verify Arr::except still works**

```bash
/www/server/php/85/bin/php artisan tinker --execute="
echo class_exists('Illuminate\Support\Arr') ? 'OK' : 'MISSING';
echo PHP_EOL;
echo method_exists('Illuminate\Support\Arr', 'except') ? 'except OK' : 'except MISSING';
"
# Expected: OK / except OK
```

- [ ] **Step 2: Commit if changes needed**

---

### Task 3c: Blueprint datetime -> dateTime

**Impact:** Only affects new migration files. Existing migrations are not affected. The single existing migration (`2019_08_19_000000_create_failed_jobs_table.php`) does not use datetime columns.

**Files:** No changes needed

- [ ] **Step 1: Confirm existing migrations unaffected**

```bash
ls /www/wwwroot/wxmuma.cn/database/migrations/
# Only 1 file, no datetime columns to worry about
```

---

## Task 4: New Feature Adoption

### Task 4a: Cache::touch() — Cache TTL Renewal

**Value:** Session cache, hot data cache can be renewed without re-writing, reducing Redis write operations.

**Files:**
- Optional optimization: code that manually manages cache TTL

- [ ] **Step 1: Scan cache usage patterns**

```bash
cd /www/wwwroot/wxmuma.cn
grep -rn "Cache::put\|Cache::remember\|Cache::add\|->put(\|->remember(" app/ --include="*.php" | head -20
```

- [ ] **Step 2: Replace cache renewal patterns with Cache::touch()**

Where code does get-then-put-to-renew, replace with:

```php
// Before
$value = Cache::get($key);
Cache::put($key, $value, $ttl);

// After (Laravel 13)
Cache::touch($key, $ttl);
```

- [ ] **Step 3: Commit**

---

### Task 4b: Bus::bulk() — Bulk Job Dispatch

**Value:** Batch dispatch queue jobs in one call, reducing Redis communication overhead.

**Files:**
- Optional optimization: code with loop dispatch patterns

- [ ] **Step 1: Scan loop dispatch patterns**

```bash
cd /www/wwwroot/wxmuma.cn
grep -rn "dispatch\|::dispatch\|Bus::dispatch" app/ --include="*.php" | head -20
```

- [ ] **Step 2: Replace loop dispatches with Bus::bulk()**

```php
use Illuminate\Support\Facades\Bus;

// Before
foreach ($items as $item) {
    SomeJob::dispatch($item);
}

// After (Laravel 13)
Bus::bulk(array_map(fn($item) => new SomeJob($item), $items));
```

- [ ] **Step 3: Commit**

---

### Task 4c: MySQL STRAIGHT JOIN

**Value:** Force MySQL to execute JOIN in specified order, optimizing complex query performance.

**Files:** Optional, only for performance-critical complex queries

- [ ] **Step 1: Identify slow queries**

```bash
cd /www/wwwroot/wxmuma.cn
grep -rn "->join(" app/ --include="*.php" | wc -l
```

- [ ] **Step 2: Use straightJoin() for performance-critical queries**

```php
// Before
DB::table('orders')->join('users', 'orders.uid', '=', 'users.id')->...

// After (Laravel 13)
DB::table('orders')->straightJoin('users', 'orders.uid', '=', 'users.id')->...
```

- [ ] **Step 3: Commit**

---

### Task 4d: eventStream() — SSE Support

**Value:** Native Server-Sent Events support for real-time notifications, order status push.

**Files:** Optional, only if real-time push is needed

- [ ] **Step 1: Evaluate SSE need**

Current project uses Horizon queue for async tasks. If real-time order status push to frontend is needed, use eventStream().

```php
use Illuminate\Support\Facades\Route;

Route::get('/events/order/{tradeNo}', function (string $tradeNo) {
    return response()->eventStream(function () use ($tradeNo) {
        while (true) {
            $order = \App\Models\Order::where('trade_no', $tradeNo)->first();
            if ($order && $order->status > 0) {
                yield 'data: ' . json_encode(['status' => $order->status]) . "\n\n";
                break;
            }
            sleep(2);
        }
    });
});
```

- [ ] **Step 2: Commit if adopted**

---

### Task 4e: Plural Morph Pivot Table Names

**Value:** MorphToMany pivot table names automatically pluralized, more consistent naming.

**Files:** Only affects newly defined morphToMany relationships

- [ ] **Step 1: Check for polymorphic relationships**

```bash
grep -rn "morphToMany\|morphedByMany" app/ --include="*.php"
```

- [ ] **Step 2: Follow new naming convention for new definitions**

---

## Task 5: Horizon/Queue Optimization

**Files:**
- Check: `config/horizon.php`
- Check: `app/Jobs/*.php` (6 Job files)

- [ ] **Step 1: Verify Horizon config compatibility**

```bash
/www/server/php/85/bin/php artisan horizon:status 2>&1
```

- [ ] **Step 2: Verify all Job classes load correctly**

```bash
/www/server/php/85/bin/php artisan tinker --execute="
\$jobs = glob(app_path('Jobs/*.php'));
foreach (\$jobs as \$job) {
    \$class = 'App\\\\Jobs\\\\' . pathinfo(\$job, PATHINFO_FILENAME);
    echo class_exists(\$class) ? 'OK: ' . basename(\$job) : 'FAIL: ' . basename(\$job);
    echo PHP_EOL;
}
"
```

- [ ] **Step 3: Restart Horizon**

```bash
/www/server/php/85/bin/php artisan horizon:terminate
# Horizon auto-restarts via Supervisor
```

- [ ] **Step 4: Commit**

---

## Task 6: Full Verification & Deployment

- [ ] **Step 1: Run all tests**

```bash
cd /www/wwwroot/wxmuma.cn
/www/server/php/85/bin/php artisan test 2>&1
```

- [ ] **Step 2: Verify route registration**

```bash
/www/server/php/85/bin/php artisan route:list 2>&1 | head -30
```

- [ ] **Step 3: Verify config cache**

```bash
/www/server/php/85/bin/php artisan config:cache 2>&1
```

- [ ] **Step 4: Verify queue can consume**

```bash
/www/server/php/85/bin/php artisan queue:work --once 2>&1
```

- [ ] **Step 5: Restart all services**

```bash
/etc/init.d/php-fpm-85 restart
/etc/init.d/nginx restart
/www/server/php/85/bin/php artisan horizon:terminate
```

- [ ] **Step 6: Push to GitHub**

```bash
cd /www/wwwroot/wxmuma.cn
git push origin master
```

- [ ] **Step 7: Verify website accessible**

```bash
curl -sI https://www.wxmuma.cn 2>&1 | head -5
# Expected: HTTP/2 200
```

---

## Rollback Plan

If upgrade fails:

```bash
# 1. Stop services
/etc/init.d/php-fpm-85 stop
/etc/init.d/nginx stop

# 2. Restore project directory
rm -rf /www/wwwroot/wxmuma.cn
cp -r /www/wwwroot/wxmuma.cn.bak.$(date +%Y%m%d) /www/wwwroot/wxmuma.cn

# 3. Restore database
mysql -h mysql3.sqlpub.com -P 3308 -u payment -p payment < /tmp/v2board_backup_$(date +%Y%m%d).sql

# 4. Restore .env
cp /tmp/.env.backup /www/wwwroot/wxmuma.cn/.env

# 5. Restart services
/etc/init.d/nginx start
/etc/init.d/php-fpm-85 start
```

---

## Estimated Time

| Task | Est. Time | Risk |
|------|-----------|------|
| Task 1: Backup | 5 min | None |
| Task 2: Composer Upgrade | 10-15 min | Low |
| Task 3: Breaking Changes | 10-20 min | Low |
| Task 4: New Features | 30-60 min | None (optional) |
| Task 5: Horizon Optimization | 10 min | Low |
| Task 6: Verify & Deploy | 15 min | Low |
| **Total** | **1.5-2 hours** | **Medium-Low** |
