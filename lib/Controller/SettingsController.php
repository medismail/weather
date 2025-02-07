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

namespace OCA\Weather\Controller;

use \OCP\IConfig;
use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;

use \OCA\Weather\Db\SettingsMapper;
use \OCA\Weather\Db\CityMapper;

class SettingsController extends Controller {

	private $userId;
	private $mapper;
	private $cityMapper;
	private $config;

	public function __construct ($appName, IConfig $config, IRequest $request, $userId, SettingsMapper $mapper, CityMapper $cityMapper) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->mapper = $mapper;
		$this->cityMapper = $cityMapper;
		$this->config = $config;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function homeSet ($city) {
		if (!$city || !is_numeric($city)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		if (!$this->cityMapper->exists($city)) {
			return new JSONResponse(array(), Http::STATUS_NOT_FOUND);
		}

		$this->mapper->setHome($this->userId, $city);
		return new JSONResponse(array("set" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function owmApiKeySet ($owmapikey) {
		$this->config->setAppValue($this->appName, 'openweathermap_api_key', $owmapikey);
		return new JSONResponse(array(
			"owmapikey" => $this->config->getAppValue($this->appName, 'openweathermap_api_key', ''),
		));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function checkWXApiKeySet ($checkwxapikey) {
		$this->config->setAppValue($this->appName, 'checkwx_api_key', $checkwxapikey);
		return new JSONResponse(array(
			"checkwxapikey" => $this->config->getAppValue($this->appName, 'checkwx_api_key', ''),
		));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function vcApiKeySet ($vcapikey) {
		$this->config->setAppValue($this->appName, 'visualcrossing_api_key', $vcapikey);
		return new JSONResponse(array(
			"vcapikey" => $this->config->getAppValue($this->appName, 'visualcrossing_api_key', ''),
		));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function wbApiKeySet ($wbapikey) {
		$this->config->setAppValue($this->appName, 'weatherbit_api_key', $wbapikey);
		return new JSONResponse(array(
			"wbapikey" => $this->config->getAppValue($this->appName, 'weatherbit_api_key', ''),
		));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function sgApiKeySet ($sgapikey) {
		$this->config->setAppValue($this->appName, 'stormglass_api_key', $sgapikey);
		return new JSONResponse(array(
			"sgapikey" => $this->config->getAppValue($this->appName, 'stormglass_api_key', ''),
		));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function metricSet ($metric) {
		$this->mapper->setMetric($this->userId, $metric);
		return new JSONResponse(array("set" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function metricGet () {
		$metric = $this->mapper->getMetric($this->userId);
		if ($metric === 0) {
			$this->mapper->setMetric($this->userId, "metric");
			$metric = "metric";
		}
		return new JSONResponse(array("metric" => $metric));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function weatherProviderSet ($provider) {
		$this->mapper->setWeatherProvider($this->userId, $provider);
		return new JSONResponse(array("set" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function weatherProviderGet () {
		$provider = $this->mapper->getWeatherProvider($this->userId);
		if ($provider === 0) {
			$this->mapper->setWeatherProvider($this->userId, "openweathermap");
			$provider = "openweathermap";
		}
		return new JSONResponse(array("provider" => $provider));
	}
};
?>
