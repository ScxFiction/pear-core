<?php
// /* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 5                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2004 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Stig Bakken <ssb@php.net>                                    |
// |                                                                      |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'PEAR/Command/Common.php';
require_once 'PEAR/Common.php';
require_once 'PEAR/Remote.php';
require_once 'PEAR/Registry.php';

class PEAR_Command_Remote extends PEAR_Command_Common
{
    // {{{ command definitions

    var $commands = array(
        'remote-info' => array(
            'summary' => 'Information About Remote Packages',
            'function' => 'doRemoteInfo',
            'shortcut' => 'ri',
            'options' => array(),
            'doc' => '<package>
Get details on a package from the server.',
            ),
        'list-upgrades' => array(
            'summary' => 'List Available Upgrades',
            'function' => 'doListUpgrades',
            'shortcut' => 'lu',
            'options' => array(
                'channel' =>
                    array(
                    'shortopt' => 'c',
                    'doc' => 'specify a channel other than the default channel',
                    'arg' => 'CHAN',
                    )
                ),
            'doc' => '[preferred_state]
List releases on the server of packages you have installed where
a newer version is available with the same release state (stable etc.)
or the state passed as the second parameter.'
            ),
        'remote-list' => array(
            'summary' => 'List Remote Packages',
            'function' => 'doRemoteList',
            'shortcut' => 'rl',
            'options' => array(
                'channel' =>
                    array(
                    'shortopt' => 'c',
                    'doc' => 'specify a channel other than the default channel',
                    'arg' => 'CHAN',
                    )
                ),
            'doc' => '
Lists the packages available on the configured server along with the
latest stable release of each package.',
            ),
        'search' => array(
            'summary' => 'Search remote package database',
            'function' => 'doSearch',
            'shortcut' => 'sp',
            'options' => array(
                'channel' =>
                    array(
                    'shortopt' => 'c',
                    'doc' => 'specify a channel other than the default channel',
                    'arg' => 'CHAN',
                    )
                ),
            'doc' => '[packagename] [packageinfo]
Lists all packages which match the search parameters.  The first
parameter is a fragment of a packagename.  The default channel
will be used unless explicitly overridden.  The second parameter
will be used to match any portion of the summary/description',
            ),
        'list-all' => array(
            'summary' => 'List All Packages',
            'function' => 'doListAll',
            'shortcut' => 'la',
            'options' => array(
                'channel' =>
                    array(
                    'shortopt' => 'c',
                    'doc' => 'specify a channel other than the default channel',
                    'arg' => 'CHAN',
                    )
                ),
            'doc' => '
Lists the packages available on the configured server along with the
latest stable release of each package.',
            ),
        'download' => array(
            'summary' => 'Download Package',
            'function' => 'doDownload',
            'shortcut' => 'd',
            'options' => array(
                'nocompress' => array(
                    'shortopt' => 'Z',
                    'doc' => 'download an uncompressed (.tar) file',
                    ),
                ),
            'doc' => '[channel/]{package|package-version}
Download a package tarball.  The file will be named as suggested by the
server, for example if you download the DB package and the latest stable
version of DB is 1.2, the downloaded file will be DB-1.2.tgz.',
            ),
        'clear-cache' => array(
            'summary' => 'Clear XML-RPC Cache',
            'function' => 'doClearCache',
            'shortcut' => 'cc',
            'options' => array(),
            'doc' => '
Clear the XML-RPC cache.  See also the cache_ttl configuration
parameter.
',
            ),
        );

    // }}}
    // {{{ constructor

    /**
     * PEAR_Command_Remote constructor.
     *
     * @access public
     */
    function PEAR_Command_Remote(&$ui, &$config)
    {
        parent::PEAR_Command_Common($ui, $config);
    }

    // }}}

    // {{{ doRemoteInfo()

    function doRemoteInfo($command, $options, $params)
    {
        if (sizeof($params) != 1) {
            return $this->raiseError("$command expects one param: the remote package name");
        }
        $savechannel = $channel = $this->config->get('default_channel');
        $reg = &$this->config->getRegistry();
        $package = $params[0];
        $channel = isset($options['channel']) ? $options['channel'] :
            $this->config->get('default_channel');
        if (!$reg->channelExists($channel)) {
            return $this->raiseError('Channel "' . $channel . '" does not exist');
        }
        $this->config->set('default_channel', $channel);
        $r = &$this->config->getRemote();
        $info = $r->call('package.info', $package);
        if (PEAR::isError($info)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError($info);
        }

        $installed = $reg->packageInfo($info['name'], null, $channel);
        $info['installed'] = $installed['version'] ? $installed['version'] : '- no -';

        $this->ui->outputData($info, $command);
        $this->config->set('default_channel', $savechannel);

        return true;
    }

    // }}}
    // {{{ doRemoteList()

    function doRemoteList($command, $options, $params)
    {
        $savechannel = $channel = $this->config->get('default_channel');
        if (isset($options['channel'])) {
            $reg = &$this->config->getRegistry();
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel '$channel' does not exist");
            }
        }
        $r = &$this->config->getRemote();
        $list_options = false;
        if ($this->config->get('preferred_state') == 'stable')
            $list_options = true;
        $available = $r->call('package.listAll', $list_options);
        if (PEAR::isError($available)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError($available);
        }
        $i = $j = 0;
        $data = array(
            'caption' => 'Available packages:',
            'border' => true,
            'headline' => array('Package', 'Version'),
            );
        foreach ($available as $name => $info) {
            $data['data'][] = array($name, isset($info['stable']) ? $info['stable'] : '-n/a-');
        }
        if (count($available)==0) {
            $data = '(no packages installed yet)';
        }
        $this->ui->outputData($data, $command);
        $this->config->set('default_channel', $savechannel);
        return true;
    }

    // }}}
    // {{{ doListAll()

    function doListAll($command, $options, $params)
    {
        $savechannel = $channel = $this->config->get('default_channel');
        if (isset($options['channel'])) {
            $reg = &$this->config->getRegistry();
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel '$channel' does not exist");
            }
        }
        $r = &$this->config->getRemote();
        $reg = &$this->config->getRegistry();
        $list_options = false;
        if ($this->config->get('preferred_state') == 'stable')
            $list_options = true;
        $available = $r->call('package.listAll', $list_options);
        if (PEAR::isError($available)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError($available);
        }
        if (!is_array($available)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError('The package list could not be fetched from the remote server. Please try again. (Debug info: "'.$available.'")');
        }
        $data = array(
            'caption' => 'All packages:',
            'border' => true,
            'headline' => array('Channel', 'Package', 'Latest', 'Local'),
            );
        $local_pkgs = $reg->listPackages($channel);

        foreach ($available as $name => $info) {
            $installed = $reg->packageInfo($name);
            $desc = $info['summary'];
            if (isset($params[$name]))
                $desc .= "\n\n".$info['description'];

            if (isset($options['mode']))
            {
                if ($options['mode'] == 'installed' && !isset($installed['version']))
                    continue;
                if ($options['mode'] == 'notinstalled' && isset($installed['version']))
                    continue;
                if ($options['mode'] == 'upgrades'
                    && (!isset($installed['version']) || $installed['version'] == $info['stable']))
                {
                    continue;
                }
            }
            $pos = array_search(strtolower($name), $local_pkgs);
            if ($pos !== false) {
                unset($local_pkgs[$pos]);
            }

            $data['data'][$info['category']][] = array(
                $channel,
                $name,
                @$info['stable'],
                @$installed['version'],
                @$desc,
                @$info['deps'],
                );
        }

        foreach ($local_pkgs as $name) {
            $info = $reg->packageInfo($name, null, $channel);
            $data['data']['Local'][] = array(
                $channel,
                $info['package'],
                '',
                $info['version'],
                $info['summary'],
                @$info['release_deps']
                );
        }

        $this->config->set('default_channel', $savechannel);
        $this->ui->outputData($data, $command);
        return true;
    }

    // }}}
    // {{{ doSearch()

    function doSearch($command, $options, $params)
    {
        if ((!isset($params[0]) || empty($params[0]))
            && (!isset($params[1]) || empty($params[1])))
        {
            return $this->raiseError('no valid search string supplied');
        };

        $savechannel = $channel = $this->config->get('default_channel');
        $reg = &$this->config->getRegistry();
        $package = $params[0];
        if (isset($options['channel'])) {
            $reg = &$this->config->getRegistry();
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel '$channel' does not exist");
            }
        }
        $r = &$this->config->getRemote();
        $available = $r->call('package.listAll', true, true);
        if (PEAR::isError($available)) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError($available);
        }
        if (!$available) {
            return $this->raiseError('no packages found');
        }
        $data = array(
            'caption' => 'Matched packages:',
            'border' => true,
            'headline' => array('Channel', 'Package', 'Stable/(Latest)', 'Local'),
            );

        foreach ($available as $name => $info) {
            $found = (!empty($package) && stristr($name, $package) !== false);
            if (!$found && !(isset($params[1]) && !empty($params[1])
                && (stristr($info['summary'], $params[1]) !== false
                    || stristr($info['description'], $params[1]) !== false)))
            {
                continue;
            };

            $installed = $reg->packageInfo($name, null, $channel);
            $desc = $info['summary'];
            if (isset($params[$name]))
                $desc .= "\n\n".$info['description'];

            $unstable = '';
            if ($info['unstable']) {
                $unstable = '/(' . $info['unstable'] . $info['state'] . ')';
            }
            if (!isset($info['stable']) || !$info['stable']) {
                $info['stable'] = 'none';
            }
            $data['data'][$info['category']][] = array(
                $channel,
                $name,
                $info['stable'] . $unstable,
                $installed['version'],
                $desc,
                );
        }
        if (!isset($data['data'])) {
            $this->config->set('default_channel', $savechannel);
            return $this->raiseError('no packages found');
        }
        $this->ui->outputData($data, $command);
        $this->config->set('default_channel', $channel);
        return true;
    }

    // }}}
    // {{{ doDownload()

    function doDownload($command, $options, $params)
    {
        if (count($params) != 1) {
            return PEAR::raiseError("download expects one argument: the package to download");
        }
        //$params[0] -> The package to download
        include_once 'PEAR/Downloader.php';
        $downloader = &new PEAR_Downloader($this->ui, array('force' => 1), $this->config);
        $errors = array();
        $downloaded = array();
        if (isset($options['nocompress'])) {
            foreach ($params as $i => $param) {
                if (!strpos($param, '.tar')) {
                    $params[$i] .= '.tar';
                }
            }
        }
        $downloader->download($params);
        $errors = $downloader->getErrorMsgs();
        if (count($errors)) {
            $err['data'] = array($errors);
            $err['headline'] = 'Download Errors';
            $this->ui->outputData($err);
            return $this->raiseError("$command failed");
        }
        $downloaded = $downloader->getDownloadedPackages();
        foreach ($downloaded as $pkg) {
            @copy($pkg['file'], $fname = getcwd() . basename($pkg['file']));
            $this->ui->outputData("File $fname downloaded", $command);
        }
        return true;
    }

    function downloadCallback($msg, $params = null)
    {
        if ($msg == 'done') {
            $this->bytes_downloaded = $params;
        }
    }

    // }}}
    // {{{ doListUpgrades()

    function doListUpgrades($command, $options, $params)
    {
        include_once "PEAR/Registry.php";
        $savechannel = $channel = $this->config->get('default_channel');
        if (isset($options['channel'])) {
            $reg = &$this->config->getRegistry();
            $channel = $options['channel'];
            if ($reg->channelExists($channel)) {
                $this->config->set('default_channel', $channel);
            } else {
                return $this->raiseError("Channel '$channel' does not exist");
            }
        }
        $remote = &$this->config->getRemote();
        $reg = &$this->config->getRegistry();
        if (empty($params[1])) {
            $state = $this->config->get('preferred_state');
        } else {
            $state = $params[1];
        }
        $caption = 'Available Upgrades';
        if (empty($state) || $state == 'any') {
            $latest = $remote->call("package.listLatestReleases");
        } else {
            $latest = $remote->call("package.listLatestReleases", $state);
            $caption .= ' (' . implode(', ', PEAR_Common::betterStates($state, true)) . ')';
        }
        $caption .= ':';
        if (PEAR::isError($latest)) {
            $this->config->set('default_channel', $savechannel);
            return $latest;
        }
        $inst = array_flip($reg->listPackages($channel));
        $data = array(
            'caption' => $caption,
            'border' => 1,
            'headline' => array('Channel', 'Package', 'Local', 'Remote', 'Size'),
            );
        foreach ((array)$latest as $pkg => $info) {
            $package = strtolower($pkg);
            if (!isset($inst[$package])) {
                // skip packages we don't have installed
                continue;
            }
            extract($info);
            $pkginfo = $reg->packageInfo($package, null, $channel);
            $inst_version = $pkginfo['version'];
            $inst_state   = $pkginfo['release_state'];
            if (version_compare("$version", "$inst_version", "le")) {
                // installed version is up-to-date
                continue;
            }
            if ($filesize >= 20480) {
                $filesize += 1024 - ($filesize % 1024);
                $fs = sprintf("%dkB", $filesize / 1024);
            } elseif ($filesize > 0) {
                $filesize += 103 - ($filesize % 103);
                $fs = sprintf("%.1fkB", $filesize / 1024.0);
            } else {
                $fs = "  -"; // XXX center instead
            }
            $data['data'][] = array($channel, $pkg, "$inst_version ($inst_state)", "$version ($state)", $fs);
        }
        if (empty($data['data'])) {
            $this->ui->outputData('No upgrades available');
        } else {
            $this->ui->outputData($data, $command);
        }
        $this->config->set('default_channel', $savechannel);
        return true;
    }

    // }}}
    // {{{ doClearCache()

    function doClearCache($command, $options, $params)
    {
        $cache_dir = $this->config->get('cache_dir');
        $verbose = $this->config->get('verbose');
        $output = '';
        if (!($dp = @opendir($cache_dir))) {
            return $this->raiseError("opendir($cache_dir) failed: $php_errormsg");
        }
        if ($verbose >= 1) {
            $output .= "reading directory $cache_dir\n";
        }
        $num = 0;
        while ($ent = readdir($dp)) {
            if (preg_match('/^xmlrpc_cache_[a-z0-9]{32}$/', $ent)) {
                $path = $cache_dir . DIRECTORY_SEPARATOR . $ent;
                $ok = @unlink($path);
                if ($ok) {
                    if ($verbose >= 2) {
                        $output .= "deleted $path\n";
                    }
                    $num++;
                } elseif ($verbose >= 1) {
                    $output .= "failed to delete $path\n";
                }
            }
        }
        closedir($dp);
        if ($verbose >= 1) {
            $output .= "$num cache entries cleared\n";
        }
        $this->ui->outputData(rtrim($output), $command);
        return $num;
    }

    // }}}
}

?>
