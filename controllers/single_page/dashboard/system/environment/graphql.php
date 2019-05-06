<?php
namespace Concrete\Package\Concrete5GraphqlWebsocket\Controller\SinglePage\Dashboard\System\Environment;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Utility\Service\Validation\Numbers;
use Concrete5GraphqlWebsocket\GraphQl\SchemaBuilder;
use Concrete5GraphqlWebsocket\GraphQl\WebsocketHelpers;
use Exception;

class Graphql extends DashboardPageController
{
    public function view()
    {
        $websocket_servers = (array)($this->app->make('config')->get('concrete.websocket.servers'));
        $this->set('websocket_servers', $websocket_servers);
        $this->set('websocket_has_servers', (bool)(count(array_keys($websocket_servers)) > 0));
        $this->set('websocket_debug', (bool)$this->app->make('config')->get('concrete.websocket.debug'));
        $this->set('graphql_dev_mode', (bool)$this->app->make('config')->get('concrete.cache.graphql_dev_mode'));
    }

    public function update_entity_settings()
    {
        if (!$this->token->validate('update_entity_settings')) {
            $this->error->add($this->token->getErrorMessage());
        }
        if (!$this->error->has()) {
            if ($this->isPost()) {
                $restartWebsocket = ' ' . t('Restart the websocket server with the button on the footer to refresh also there GraphQL schema');
                $gdm = $this->post('GRAPHQL_DEV_MODE') === 'yes';
                $wd = $this->post('WEBSOCKET_DEBUG') === 'yes';
                $w = $this->post('WEBSOCKET') === 'yes';

                if ($this->request->request->get('refresh')) {
                    SchemaBuilder::refreshSchemaMerge();
                    $this->flash('success', t('GraphQL cache cleared, GraphQL schema updated.') . ($w ? $restartWebsocket : ''));
                    $this->redirect('/dashboard/system/environment/graphql', 'view');
                } else {
                    $this->app->make('config')->save('concrete.cache.graphql_dev_mode', $gdm);
                    if ($gdm) {
                        SchemaBuilder::refreshSchemaMerge();
                    }

                    if ($w) {
                        $this->app->make('config')->save('concrete.websocket.debug', $wd);
                        $servers = (array)($this->app->make('config')->get('concrete.websocket.servers'));
                        $this->app->make('config')->save('concrete.websocket.servers', array());
                        $websocketsPorts = $this->post('WEBSOCKET_PORTS');
                        foreach ($websocketsPorts as $websocketsPort) {
                            $hasServerAlready = false;
                            foreach ($servers as $port => $pid) {
                                if ($port == $websocketsPort) {
                                    $hasServerAlready = true;
                                    $this->app->make('config')->save('concrete.websocket.servers.' . $websocketsPort, $pid);
                                }
                            }
                            if (!$hasServerAlready) {
                                $this->app->make('config')->save('concrete.websocket.servers.' . $websocketsPort, '');
                            }
                        }
                    } else {
                        $this->app->make('config')->save('concrete.websocket.debug', false);
                        $servers = (array)($this->app->make('config')->get('concrete.websocket.servers'));
                        $this->app->make('config')->save('concrete.websocket.servers', array());
                        foreach ($servers as $port => $pid) {
                            $pid = (int)$pid;
                            if ($pid > 0) {
                                WebsocketHelpers::stop($pid);
                            }
                        }
                    }
                    $this->flash('success', t('Settings updated.') . ($w && $gdm ? $restartWebsocket : ''));
                    $this->redirect('/dashboard/system/environment/graphql', 'view');
                }
            }
        } else {
            $this->set('error', [$this->token->getErrorMessage()]);
        }
    }

    public function restartWebsocketServer()
    {
        if (!$this->token->validate('ccm-restart_websockets')) {
            throw new Exception($this->token->getErrorMessage());
        }
        $valn = $this->app->make(Numbers::class);
        $rawPids = $this->request->request->get('pids');
        if (!is_array($rawPids)) {
            throw new Exception(sprintf('Invalid parameters: %s', 'pids'));
        }
        $pids = [];
        foreach ($rawPids as $rawPid) {
            if (!$valn->integer($rawPid, 0)) {
                throw new Exception(sprintf('Invalid parameters: %s', 'pids'));
            }
            $pid = (int)$rawPid;
            if (in_array($pid, $pids, true)) {
                throw new Exception(sprintf('Invalid parameters: %s', 'pids'));
            }
            $pids[] = $pid;
        }

        $success = true;
        foreach ($pids as $pid) {
            $pid = (int)$pid;
            $currentPort = 0;

            $servers = (array)($this->app->make('config')->get('concrete.websocket.servers'));
            foreach ($servers as $port => $oldPid) {
                if ($pid == $oldPid) {
                    $currentPort = $port;
                    $this->app->make('config')->save('concrete.websocket.servers.' . $port, '');
                }
            }
            $success &= WebsocketHelpers::stop($pid);

            if (!$success) {
                throw new Exception(sprintf('Did not work use "sudo kill %s" on the server console and refresh this site afterwards.', $pid));
            } else if ($currentPort > 0) {
                WebsocketHelpers::start($currentPort);
            }
        }
        if ($success) {
            $this->flash('success', t('Websocket server restarted and GraphQL Schema reloaded. Refresh this site to get the new pids if you did not get it already.'));
        } else {
            $this->flash('error', t('Did not work use "sudo kill pid" on the server console and refresh this site afterwards.'));
        }

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    public function stopWebsocketServer()
    {
        if (!$this->token->validate('ccm-stop_websocket')) {
            throw new Exception($this->token->getErrorMessage());
        }
        $valn = $this->app->make(Numbers::class);
        $rawPids = $this->request->request->get('pids');
        if (!is_array($rawPids)) {
            throw new Exception(sprintf('Invalid parameters: %s', 'pids'));
        }
        $pids = [];
        foreach ($rawPids as $rawPid) {
            if (!$valn->integer($rawPid, 0)) {
                throw new Exception(sprintf('Invalid parameters: %s', 'pids'));
            }
            $pid = (int)$rawPid;
            if (in_array($pid, $pids, true)) {
                throw new Exception(sprintf('Invalid parameters: %s', 'pids'));
            }
            $pids[] = $pid;
        }

        $success = true;
        foreach ($pids as $pid) {
            $pid = (int)$pid;

            $servers = (array)($this->app->make('config')->get('concrete.websocket.servers'));
            foreach ($servers as $port => $oldPid) {
                if ($pid == $oldPid) {
                    $this->app->make('config')->save('concrete.websocket.servers.' . $port, '');
                }
            }
            $success &= WebsocketHelpers::stop($pid);

            if (!$success) {
                throw new Exception(sprintf('Did not work use "sudo kill %s" on the server console and refresh this site afterwards.', $pid));
            }
        }
        if ($success) {
            $this->flash('success', t('Websocket server stopped.'));
        } else {
            $this->flash('error', t('Did not work use "sudo kill pid" on the server console and refresh this site afterwards.'));
        }

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    public function startWebsocketServer()
    {
        if (!$this->token->validate('ccm-start_websocket')) {
            throw new Exception($this->token->getErrorMessage());
        }
        $valn = $this->app->make(Numbers::class);
        $rawPorts = $this->request->request->get('ports');
        if (!is_array($rawPorts)) {
            throw new Exception(sprintf('Invalid parameters: %s', 'ports'));
        }
        $ports = [];
        foreach ($rawPorts as $rawPort) {
            if (!$valn->integer($rawPort, 0)) {
                throw new Exception(sprintf('Invalid parameters: %s', 'ports'));
            }
            $port = (int)$rawPort;
            if (in_array($port, $ports, true)) {
                throw new Exception(sprintf('Invalid parameters: %s', 'ports'));
            }
            $ports[] = $port;
        }

        foreach ($ports as $port) {
            $port = (int)$port;
            WebsocketHelpers::start($port);
            sleep(1);
        }

        $this->flash('success', t('Websocket server started and GraphQL Schema reloaded. Refresh this site to get the new pids if you did not get it already.'));

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    public function removeWebsocketServer()
    {
        if (!$this->token->validate('ccm-remove_websocket')) {
            throw new Exception($this->token->getErrorMessage());
        }
        $valn = $this->app->make(Numbers::class);
        $rawPid = $this->request->request->get('pid');
        $rawPort = $this->request->request->get('port');

        if (!$valn->integer($rawPort, 0)) {
            throw new Exception(sprintf('Invalid parameters: %s', 'port'));
        }
        $port = (int)$rawPort;
        $pid = (int)$rawPid;

        $servers = (array)($this->app->make('config')->get('concrete.websocket.servers'));
        $this->app->make('config')->save('concrete.websocket.servers', array());
        foreach ($servers as $oldPort => $oldPid) {
            if ($pid > 0 && $pid == $oldPid) {
                $success = WebsocketHelpers::stop($pid);

                if (!$success) {
                    throw new Exception(sprintf('Did not work use "sudo kill %s" on the server console and refresh this site afterwards.', $pid));
                }
            } else if ($port !== $oldPort) {
                $this->app->make('config')->save('concrete.websocket.servers.' . $oldPort, $oldPid);
            }
        }

        if (!$pid || $success) {
            $this->flash('success', t('Websocket server removed'));
        } else if ($pid > 0) {
            $this->flash('error', t('Could not remove websocket server'));
        }

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }
}