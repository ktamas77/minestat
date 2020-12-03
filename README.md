# MineStat

Simple statistics for your mining operations.

### Currently supported:
 * Ethos StatsPanel
 * Ethermine
 * Nanopool-Siacoin
 * MiningPoolHub

### To install:
* Please see `config.yaml.sample` for configuration details.
* Run `composer update` to install required libraries

### Requirements:
* PHP 7.0+

Run local / dev:
`composer start`

### Usage:

* Load standard page:
`http://localhost:8080`

* Show expected monthly payments in USD:
`http://localhost:8080/pay`

* Reload page in every minute:
`http://localhost:8080/reload`
 
