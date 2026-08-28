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
