# Yireo_ThemeCommands module

**Magento 2 module to add CLI commands to manage themes from the command-line**

## Installation
```bash
composer require yireo/magento2-theme-commands
bin/magento module:enable Yireo_ThemeCommands
```

## Usage
List all themes:
```bash
bin/magento theme:list
```

List all assigned themes (aka design configurations):
```bash
bin/magento theme:design_config
```

*The `theme:design_config` output also shows an **Override** column, which identifies whether a specific value (like, a theme ID for a specific Store View) is indeed overriding the default or not.*

Change the current theme to `Magento/luma` for all scopes:
```bash
bin/magento theme:change Magento/luma
```

Note that the `theme:change` command also includes a flag `--reset` (valid only without additional parameters) which resets all stores to the default, so that only 1 theme is active:
```bash
bin/magento theme:change --reset -- Magento/luma
```

Change the current theme to `Hyva/default` for the StoreView with ID 1:
```bash
bin/magento theme:change Hyva/default 1 stores
```

Create a new theme:
```bash
bin/magento theme:create --theme Yireo/example --parent Magento/luma --application frontend
bin/magento theme:change Yireo/example
```
