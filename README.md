# php-blade (crazyfd/php-blade)

Laravel [Blade](https://laravel.com/docs/blade) 模板引擎的独立 PHP 版本（webman blade），可用于 Webman 等非 Laravel 环境：安装即得 Blade 视图渲染、`blade:cache` / `blade:clear` 命令。基于 [jenssegers/blade](https://github.com/jenssegers/blade) / [webman/blade](https://github.com/webman-php/blade) 升级维护，感谢原作者。

> 支持 **illuminate/view 10.x / 11.x / 12.x / 13.x**。

## 背景

我们的业务迭代很快，之前技术栈是 Laravel + Octane。但由于业务场景比较特殊、流量较大，服务经常遇到性能瓶颈和稳定性问题，在现有硬件资源无法进一步扩容的情况下，我们经过多方面评估，最终将 Laravel 迁移到了 [Webman](https://www.workerman.net/doc/webman/)。

迁移之后，Webman 在高并发和高性能场景下的表现确实非常优秀，也很好地解决了我们之前遇到的一些问题。

但在实际迁移过程中，我们也发现了一个比较明显的问题：Laravel 生态经过多年的发展，已经形成了一套非常完善、成熟的组件体系，而 Webman 生态中的部分通用组件存在维护不及时、版本兼容性不足，以及与新版 `illuminate/*` 组件适配不完善等情况。

与此同时，我们并不希望因为从 Laravel 迁移到 Webman，就放弃 Laravel 中成熟的开发习惯和生态能力。更重要的是，我们希望 Laravel → Webman 的迁移能够尽可能平滑，让原有项目的代码、业务逻辑和成熟组件得到最大程度的复用，而不是为了适配 Webman 而进行大量重构和重复开发。

因此，我们决定围绕现代 Laravel / Illuminate 生态进行适配和维护，在保持 Laravel 原有使用方式和开发体验的基础上，让这些组件能够更好地运行在 Webman 环境中。通过这种方式，尽可能降低 Laravel 项目迁移到 Webman 的改造成本，让原有代码**少改甚至不改即可继续使用**。

不仅仅是解决当前项目的兼容性问题，更是逐步补齐 Webman 生态中缺失的通用组件，并长期维护一批高质量、现代化的 PHP 组件包，同时将 Webman 作为官方支持的一等集成场景。

简单来说，我们希望做到：

> **享受 Webman 的高性能，同时保留 Laravel 成熟的生态、开发体验和代码资产，让 Laravel → Webman 不再意味着大规模重写。**


## 环境要求

- PHP >= 8.1
- illuminate/view ^10.0 || ^11.0 || ^12.0 || ^13.0

注意：实际 PHP 最低版本还取决于安装的 illuminate major 版本；例如 illuminate/view 13.x 要求 PHP >= 8.3。

Webman 集成需要：

- workerman/webman-framework ^2.0
- webman/console ^2.0（使用 `blade:cache` / `blade:clear` 命令时）

## 安装

```bash
composer require crazyfd/php-blade
```

## 使用

### 全局 helper（Laravel 风格）

独立环境（非 webman）下提供 `view()` / `blade()` 全局函数，写法与 Laravel 一致：

```php
echo view('homepage', ['name' => 'John Doe'])->render();
// 或直接输出（View 实现了 __toString）
echo view('homepage', ['name' => 'John Doe']);
```

首次调用 `blade($viewPath, $cachePath)` 可指定视图/缓存目录（默认 `<cwd>/views` 与系统临时目录），之后全局共享同一实例：

```php
blade(__DIR__ . '/views', __DIR__ . '/cache'); // 初始化（可选）

echo view('homepage', ['name' => 'John Doe']);
```

> 在 webman 中，框架自带的 `view()`（返回 `Response`）优先生效，本包不会覆盖；请按下节方式使用。

## 框架集成

> 目前阶段官方集成以 **Webman** 为主，Webman 用户开箱即用；其他框架暂无官方集成包，可直接实例化核心类使用。

### Webman

安装后自动生成 `config/plugin/crazyfd/blade/app.php`，可配置**视图缓存目录**等编译选项：

```php
<?php

return [
    'enable' => true,

    // 编译后的视图缓存目录
    'cache_path' => runtime_path() . '/views',

    // 是否缓存编译结果（false 时每次重新编译，仅建议开发环境使用）
    'cache' => true,

    // 编译文件扩展名
    'compiled_extension' => 'php',

    // 检查缓存时间戳，模板更新后自动重新编译
    'check_timestamps' => true,
];
```

配置 `config/view.php` 使用本包的 handler（替代 `support\view\Blade`，读取上述配置）：

```php
use Jenssegers\Blade\Webman\View as BladeView;

return [
    'handler' => BladeView::class,
];
```

控制器中按 Webman 约定渲染，模板放在 `app/view/` 下：

```php
return view('user/profile', ['name' => 'John Doe']);
```

> 视图名 `/` 与 `.` 分隔符均可：`user/profile`（Webman 惯例）与 `user.profile`（Laravel 惯例）解析到同一个模板，从 Laravel 迁移的代码无需修改。

自定义指令 / 条件通过 `config/view.php` 的 `extension` 回调注册（`blade:cache` 预编译时同样生效）：

```php
use Jenssegers\Blade\Blade;

return [
    'handler' => \Jenssegers\Blade\Webman\View::class,
    'extension' => function (Blade $blade): void {
        $blade->directive('shout', fn ($expression) => "<?= strtoupper((string) ($expression)) ?>");
    },
];
```

命令行管理编译缓存：

```bash
php webman blade:cache   # 预编译所有 Blade 模板（上线前执行可提升首次访问速度）
php webman blade:clear   # 清空编译缓存
```

### 直接实例化

```php
use Jenssegers\Blade\Blade;

$blade = new Blade('/path/to/views', '/path/to/cache');

echo $blade->render('homepage', ['name' => 'John Doe']);
```

### 常用 API

```php
$blade->make('view.name', $data)->render(); // 渲染视图
$blade->render('view.name', $data);         // 等价快捷方式
$blade->exists('view.name');                // 视图是否存在
$blade->share('key', $value);               // 共享数据
$blade->compiler();                         // BladeCompiler 实例
$blade->directive('name', $handler);        // 自定义指令
$blade->if('admin', $callback);             // 自定义条件
$blade->component(Alert::class, 'alert');   // 注册组件
$blade->addNamespace('namespace', $path);   // 命名空间视图
```

## Blade 模板

模板语法与 Laravel 完全一致（`@if` / `@foreach` / `@include` / `{{ }}` 转义 / `{!! !!}` 原样输出 / 组件 / 插件指令等），详见 [Laravel Blade 文档](https://laravel.com/docs/blade)。

注意：指令的 `@` 前不能紧跟字母（`yes@else` 不会编译，需写成 `yes @else` 或换行），这是 Blade 编译器原生行为。

## 架构分层

- `Jenssegers\Blade\Blade` 是核心渲染入口，可在普通 PHP 项目中独立使用
- `Jenssegers\Blade\Webman\View` 是 Webman view handler，只负责读取 Webman 配置和适配 Webman 渲染约定
- `Jenssegers\Blade\Webman\Command\*` 只负责 Webman 下的模板缓存命令
- `src/config/plugin/crazyfd/blade` 只用于 Webman 插件配置发布

## 兼容矩阵

| Package Version | PHP | Framework Integration | illuminate/view | Status |
|---|---|---|---|---|
| 2.x | >=8.1; 13.x requires >=8.3 | Webman ^2.0 | 10.x - 13.x | Maintained |

## 与官方 webman/blade 的差异

- 支持 illuminate/view 13.x（官方尚未发布支持）
- 修复 `ViewServiceProvider` 中 `DynamicComponent` 未导入导致 dynamic-component 注册到不存在类的 bug
- BladeCompiler 构造参数与 Laravel 13 对齐（支持 `view.cache` / `view.compiled_extension` / `view.check_cache_timestamps` 配置）
- PHP 8.1+ 原生类型声明
- 完整测试覆盖（渲染、指令、组件、Webman 集成层、跨 illuminate 版本矩阵）

## 运行测试

```bash
composer install
composer test
```

## License

MIT
