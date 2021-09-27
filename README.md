# GTT Laravel Client
Client per serveis de GTT.

*Credits*: Ajuntament de Tarragona.


## Instalació

```bash
composer require ajtarragona/gtt-client
```

## Configuració

Pots configurar el paquet a través de l'arxiu `.env` de l'aplicació. Aquests son els parámetres disponibles :
```bash
GTT_DEBUG 
GTT_WS_URL
GTT_CERT_PATH //ruta del certificado desde storage/app
GTT_CERT_PASSWORD 
```
Alternativament, pots publicar l'arxiu de configuració del paquet amb la comanda:

```bash
php artisan vendor:publish --tag=ajtarragona-gtt-config
```

Això copiarà l'arxiu a `config/gtt.php`.


## Ús

Un cop configurat, el paquet està a punt per fer-se servir. 

Ho pots fer de les següents maneres:


### Vía Injecció de dependències:

Als teus controlladors, helpers, model:

```php
use Ajtarragona\GTT\Services\GTTService;

...
public function test(GTTService $gtt){
	$datos=$tercers->getDatosContribuyente(123456);
	...
}
```


### A través d'una `Facade`:

```php
use GTT;
...
public function test(){
	$datos=GTT::getDatosContribuyente(123456);
	...
}
```



### Vía funció `helper`:
```php
...
public function test(){
	$datos=gtt()->getDatosContribuyente(123456);
	...
}


## Funcions

### AccedeTercers
Funció | Paràmetres | Retorn 
--- | --- | --- 
**getDatosContribuyente** | `id` | dades
