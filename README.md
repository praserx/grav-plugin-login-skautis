# Grav SkautIS Login Plugin

`Login SkautIS` is a [Grav][grav] Plugin that allows to login via SkautIS (like SSO login).

# Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `login-skautis`.

You should now have all the plugin files under

	/your/site/grav/user/plugins/pagination

>> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav), the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) plugins, and a theme to be installed in order to operate.

# Config Defaults

```
enabled: true
provider_url: https://is.skaut.cz/Login/
app_id: "xxxx-xxxxxxxx-xxxxxxxx-xxxx-xxxx"
parent_unit: "123.01.01"
parent_unit_match_level: 0
```
