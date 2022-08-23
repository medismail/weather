<?php
/**
 * ownCloud - Weather
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Loic Blot <loic.blot@unix-experience.fr>
 * @copyright Loic Blot 2015
 */

namespace OCA\Weather\AppInfo;

$application = new Application();

$application->registerRoutes($this, array('routes' => array(
	array('name' => 'city#index',			'url' => '/',				'verb' => 'GET'),

	array('name' => 'city#getall',			'url' => '/city/getall',		'verb' => 'GET'),
	array('name' => 'city#add',			'url' => '/city/add',			'verb' => 'POST'),
	array('name' => 'city#delete',			'url' => '/city/delete',		'verb' => 'POST'),
	array('name' => 'city#getnamefromgeo',		'url' => '/city/getnamefromgeo',	'verb' => 'GET'),
	array('name' => 'city#getnamefromip',		'url' => '/city/getnamefromip',		'verb' => 'GET'),

	array('name' => 'weather#get',			'url' => '/weather/get',		'verb' => 'GET'),

	array('name' => 'settings#homeset',		'url' => '/settings/home/set',		'verb' => 'POST'),
	array('name' => 'settings#owmapikeyset',	'url' => '/settings/owmapikey',		'verb' => 'POST'),
	array('name' => 'settings#checkwxapikeyset',	'url' => '/settings/checkwxapikey',	'verb' => 'POST'),
	array('name' => 'settings#vcapikeyset',		'url' => '/settings/vcapikey',		'verb' => 'POST'),
	array('name' => 'settings#wbapikeyset',		'url' => '/settings/wbapikey',		'verb' => 'POST'),
	array('name' => 'settings#sgapikeyset',		'url' => '/settings/sgapikey',		'verb' => 'POST'),
	array('name' => 'settings#metricset',		'url' => '/settings/metric/set',	'verb' => 'POST'),
	array('name' => 'settings#metricget',		'url' => '/settings/metric/get',	'verb' => 'GET'),
	array('name' => 'settings#weatherproviderset',	'url' => '/settings/provider/set',	'verb' => 'POST'),
	array('name' => 'settings#weatherproviderget',	'url' => '/settings/provider/get',	'verb' => 'GET'),
)));
?>
