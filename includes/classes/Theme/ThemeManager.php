<?php
namespace Godyar\Theme;
use Godyar\Services\SettingsService;
class ThemeManager {
  private static ?array $active = null;
  public static function bootstrap(): void {
    self::$active = [
      'name' => SettingsService::get('theme.active','default'),
      'vars' => [
        'turquoise' => SettingsService::get('theme.primary','#15baba'),
        'header_footer_opacity' => (string)SettingsService::get('theme.opacity.header_footer','0.60'),
        'interface_opacity' => (string)SettingsService::get('theme.opacity.interface','0.02'),
        'blocks_opacity' => (string)SettingsService::get('theme.opacity.blocks','0.035')
      ]
    ];
  }
  public static function activeTheme(): array { return self::$active ?? []; }
}
