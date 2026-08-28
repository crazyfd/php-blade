# Changelog

本项目所有重要变更都会记录在此文件中。

## [2.1.0] - 2026-08-28

### Added

- illuminate/view 13.x 支持（同时兼容 10.x / 11.x / 12.x）
- 新增 Laravel 风格全局 helper：独立环境下可直接使用 `view('template', $data)` 与 `blade()`（webman 内自动让位给框架自带 `view()`，不冲突）
- Webman 插件化：`config/plugin/crazyfd/blade/app.php` 可配置视图缓存目录（`cache_path`）及编译选项（`cache` / `compiled_extension` / `check_timestamps`）
- 新增 `Jenssegers\Blade\Webman\View` 视图 handler（读取上述配置，替代 `support\view\Blade`）
- 新增命令 `php webman blade:cache`（预编译全部模板）/ `php webman blade:clear`（清空编译缓存）
- `Blade` 构造函数新增可选 `$options` 参数，可传入编译配置（向后兼容）
- PHPUnit 测试套件：渲染、转义、指令、include、自定义 directive/if、组件、缓存、helper
- BladeCompiler 构造参数与 Laravel 13 对齐，支持 `view.cache`、`view.compiled_extension`、`view.check_cache_timestamps` 配置项

### Fixed

- `ViewServiceProvider` 中 `DynamicComponent` 缺少 use 导入，`dynamic-component` 被注册到不存在类的问题

### Changed

- 最低 PHP 版本从 7.0 提升至 8.1
- `Config` 偏移量方法使用 PHP 8.1 原生类型声明，移除 `#[\ReturnTypeWillChange]`

### Breaking Changes

- 包更名：`webman/blade` → `crazyfd/php-blade`（namespace 保持 `Jenssegers\Blade` 不变，业务代码无需修改）
