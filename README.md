# Yireo_ThemeCommands module

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-theme-commands)
<!-- badges.specs.end -->


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

When you reset to a custom theme, and recepeive an error like:
```bash
bin/magento theme:change --reset <vendor/<theme>                                
                                                 
  Not enough arguments (missing: "theme_name").  
                                                 
theme:change [--reset [RESET]] [--] <theme_name> [<scope> [<scope_id>]]
```

you'll need to use the following command; 
```bash
bin/magento theme:change --reset Magento/luma <vendor/<theme>
```


Change the current theme to `Hyva/default` for the StoreView with ID 1:
```bash
bin/magento theme:change Hyva/default stores 1
```

Create a new theme:
```bash
bin/magento theme:create --theme Yireo/example --parent Magento/luma --application frontend
bin/magento theme:change Yireo/example
```

## Caveats
When a new theme has been created, it might have not been *discovered* by Magento. For instance, the output of the `theme:list` command is not showing this new theme. Run `bin/magento setup:upgrade` first.

## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ThemeCommands/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ThemeCommands/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ThemeCommands/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ThemeCommands/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_ThemeCommands/compile.yml?label=compile)
<!-- badges.test.end -->
