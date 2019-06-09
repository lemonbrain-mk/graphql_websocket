<?php

namespace Concrete\Package\Concrete5GraphqlWebsocket;

use Concrete\Core\Http\ServerInterface;
use Concrete\Core\Package\Package;
use Concrete\Core\Routing\RouterInterface;
use Concrete5GraphqlWebsocket\SchemaBuilder;
use Concrete5GraphqlWebsocket\Websocket;
use Concrete5GraphqlWebsocket\WebsocketHelpers;
use Concrete5GraphqlWebsocket\PackageHelpers;

class Controller extends Package
{
    protected $appVersionRequired = '8.5.1';
    protected $pkgVersion = '1.2.2';
    protected $pkgHandle = 'concrete5_graphql_websocket';
    protected $pkgName = 'GraphQL with Websocket';
    protected $pkgDescription = 'This Package brings the power of GraphQL and Websockets to Concrete5';
    protected $pkgAutoloaderRegistries = [
        'src' => '\Concrete5GraphqlWebsocket',
    ];

    public function on_start()
    {
        $this->app->make(RouterInterface::class)->register('/graphql', 'Concrete5GraphqlWebsocket\Api::view');
        PackageHelpers::setPackageHandle($this->pkgHandle);
        Websocket::run();
    }

    public function install()
    {
        parent::install();
        $this->installXML();
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installXML();
    }

    public function uninstall()
    {
        $config = $this->getFileConfig();
        $config->save('websocket.debug', false);
        $servers = (array) $config->get('concrete.websocket.servers');
        foreach ($servers as $port => $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                WebsocketHelpers::stop($pid);
            }
        }
        $config->save('concrete.websocket.servers', []);
        SchemaBuilder::deleteSchemaFile();

        parent::uninstall();
    }

    private function installXML()
    {
        $this->installContentFile('config/install.xml');
    }
}
