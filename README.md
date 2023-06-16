# Yireo_ThemeCommands module

CLI commands to manage themes from the command-line (work in progress)

## Installation
```bash
composer require yireo/magento2-theme-commands
bin/magento module:enable Yireo_ThemeCommands
```

## Usage
Change the current theme to `Magento/luma` for all scopes:
```bash
bin/magento theme:change Magento/luma
```

Change the current theme to `Hyva/default` for the StoreView with ID 1:
```bash
bin/magento theme:change Hyva/default 1 stores
```
