<?php

namespace Ajtarragona\GTT;

use Illuminate\Support\ServiceProvider;

class GTTServiceProvider extends ServiceProvider
{
    

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        
        

        //cargo rutas
        $this->loadRoutesFrom(__DIR__.'/routes.php');


        //publico configuracion         
        $this->publishes([
            __DIR__.'/Config/gtt.php' => config_path('gtt.php'),
        ], 'ajtarragona-gtt-config');

        

        
        


       
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
       	
        //defino facades
        $this->app->bind('gtt', function(){
            return new \Ajtarragona\GTT\Services\GTTService;
        });
        

        //helpers
        foreach (glob(__DIR__.'/Helpers/*.php') as $filename){
            require_once($filename);
        }


        
        if (file_exists(config_path('gtt.php'))) {
            $this->mergeConfigFrom(config_path('gtt.php'), 'gtt');
        } else {
            $this->mergeConfigFrom(__DIR__.'/Config/gtt.php', 'gtt');
        }
        
    }
}
