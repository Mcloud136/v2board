<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ThemeService
{
    private $path;
    private $theme;

    public function __construct($theme)
    {
        $this->theme = $theme;
        $this->path = $path = public_path('theme/');
    }

    public function init()
    {
        $themeConfigFile = $this->path . "{$this->theme}/config.json";
        if (!File::exists($themeConfigFile)) abort(500, "{$this->theme}主题不存在");
        $themeConfig = json_decode(File::get($themeConfigFile), true);
        if (!isset($themeConfig['configs']) || !is_array($themeConfig)) abort(500, "{$this->theme}主题配置文件有误");
        $configs = $themeConfig['configs'];
        $data = [];
        foreach ($configs as $config) {
            $data[$config['field_name']] = isset($config['default_value']) ? $config['default_value'] : '';
        }

        // 消毒字符串值防止 PHP 代码注入
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = str_replace(['<?php', '<?', '?>', '<?PHP', '<?=', '`', '${'], '', $v);
            }
        }

        $data = var_export($data, 1);
        try {
            if (!File::put(base_path() . "/config/theme/{$this->theme}.php", "<?php\n return $data ;")) {
                abort(500, "{$this->theme}初始化失败");
            }
        } catch (\Exception $e) {
            abort(500, '请检查V2Board目录权限');
        }

        try {
            Artisan::call('config:cache');
            // config:cache 是同步操作，完成后 config() 立即可用
            if (!config("theme.{$this->theme}")) {
                abort(500, "{$this->theme} 主题配置加载失败");
            }
        } catch (\Exception $e) {
            abort(500, "{$this->theme}初始化失败");
        }
    }
}
