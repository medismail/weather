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
use \OCP\IL10N;

use \OCA\Weather\Db\CityMapper;
use \OCA\Weather\Db\SettingsMapper;

use \OCA\Weather\Controller\IntermediateController;

class WeatherController extends IntermediateController {

	private $userId;
	private $mapper;
	private $settingsMapper;
	private $metric;
	private $config;
	private $trans;
	private static $apiWeatherURL = "http://api.openweathermap.org/data/2.5/weather?mode=json&q=";
	private static $apiForecastURL = "http://api.openweathermap.org/data/2.5/forecast?mode=json&q=";
	private static $apiCheckWXURL = "https://api.checkwx.com/metar/";
	private static $apiAirPollutionURL = "http://api.openweathermap.org/data/2.5/air_pollution?mode=json&";
        private static $apiVCURL = "https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/";

	public function __construct ($appName, IConfig $config, IRequest $request, $userId, CityMapper $mapper, SettingsMapper $settingsMapper, IL10N $trans) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->mapper = $mapper;
		$this->settingsMapper = $settingsMapper;
		$this->metric = $settingsMapper->getMetric($this->userId);
		$this->config = $config;
		$this->trans = $trans;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get($name) {
		$cityInfos = $this->getCityInformations($name);
		if (!$cityInfos) {
			return new JSONResponse(array(), $this->errorCode);
		}
		return new JSONResponse($cityInfos);
	}

	public function getLanguageCode() {
        	return $this->trans->getLanguageCode();
	}

	private function getCityInformations ($name) {

		$apiKey = $this->config->getAppValue($this->appName, 'openweathermap_api_key');
		$apiMetarKey = $this->config->getAppValue($this->appName, 'checkwx_api_key');
		$apiVCKey = $this->config->getAppValue($this->appName, 'visualcrossing_api_key');

		$name = preg_replace("[ ]",'%20',$name);

		$openWeatherMapLang = array("ar", "bg", "ca", "cz", "de", "el", "en", "fa", "fi", "fr", "gl", "hr", "hu", "it", "ja", "kr", "la", "lt", "mk", "nl", "pl", "pt", "ro", "ru", "se", "sk", "sl", "es", "tr", "ua", "vi");
		$currentLang = \OC::$server->getL10N('core')->getLanguageCode();

		if (preg_match("/_/i", $currentLang)) {
			$currentLang = strstr($currentLang, '_', true);
		}

		if (in_array($currentLang, $openWeatherMapLang)) {
			$reqContent = $this->curlGET(WeatherController::$apiWeatherURL.$name."&APPID=".$apiKey."&units=".$this->metric."&lang=".$currentLang);
		}
		else {
			$reqContent = $this->curlGET(WeatherController::$apiWeatherURL.$name."&APPID=".$apiKey."&units=".$this->metric);
		}

		if ($reqContent[0] != Http::STATUS_OK) {
			$this->errorCode = $reqContent[0];
			return null;
		}

		$cityDatas = json_decode($reqContent[1], true);
		$cityDatas["forecast"] = array();

		if ($apiVCKey) {
			$forecast = json_decode(file_get_contents(WeatherController::$apiVCURL.$name."?key=".$apiVCKey."&unitGroup=".$this->metric), true);
			if (isset($forecast['currentConditions'])) {
				$cityDatas['main']['uvindex'] = $forecast['currentConditions']['uvindex'];
				$cityDatas['main']['dew'] = $forecast['currentConditions']['dew'];
				$cityDatas['main']['solarradiation'] = $forecast['currentConditions']['solarradiation'];
				$cityDatas['main']['solarenergy'] = $forecast['currentConditions']['solarenergy'] ? $forecast['currentConditions']['solarenergy'] : 0;
				$cityDatas['main']['cloudcover'] = $forecast['currentConditions']['cloudcover'];
			}
			if (isset($forecast['days'])) {
				$maxFC = count($forecast['days']);
				for ($i = 0; $i < $maxFC; $i++) {
					$cityDatas['forecast'][] = array(
						'date' => $this->StrTimeToString($forecast['days'][$i]['datetime']),
						'weather' => $forecast['days'][$i]['description'],
						'temperature' => $forecast['days'][$i]['temp'],
						'temperature_feelslike' => $forecast['days'][$i]['feelslike'],
						'temperature_min' => $forecast['days'][$i]['tempmin'],
						'temperature_max' => $forecast['days'][$i]['tempmax'],
						'precipitation' => $forecast['days'][$i]['precip'],
						'pressure' => $forecast['days'][$i]['pressure'],
						'humidity' => $forecast['days'][$i]['humidity'],
						'uvindex' => $forecast['days'][$i]['uvindex'],
						'wind' => array(
							'speed' => $forecast['days'][$i]['windspeed'],
							'desc' => $this->windDegToString($forecast['days'][$i]['winddir'])
						)
					);
				}
			}
		} else {
			if (in_array($currentLang, $openWeatherMapLang)) {
				$forecast = json_decode(file_get_contents(WeatherController::$apiForecastURL.$name."&APPID=".$apiKey."&units=".$this->metric."&lang=".$currentLang), true);
			}
			else {
				$forecast = json_decode(file_get_contents(WeatherController::$apiForecastURL.$name."&APPID=".$apiKey."&units=".$this->metric), true);
			}

			if ($forecast['cod'] == '200' && isset($forecast['cnt']) && is_numeric($forecast['cnt'])) {
				// Show only 8 values max
				// @TODO: setting ?
				$maxFC = $forecast['cnt'] > 40 ? 40 : $forecast['cnt'];
				for ($i = 0; $i < $maxFC; $i++) {
					$cityDatas['forecast'][] = array(
						'date' => $this->UnixTimeToString($forecast['list'][$i]['dt']),
						'weather' => $forecast['list'][$i]['weather'][0]['description'],
						'temperature' => $forecast['list'][$i]['main']['temp'],
						'temperature_feelslike' => $forecast['list'][$i]['main']['feels_like'],
						'temperature_min' => $forecast['list'][$i]['main']['temp_min'],
						'temperature_max' => $forecast['list'][$i]['main']['temp_max'],
						'pressure' => $forecast['list'][$i]['main']['pressure'],
						'humidity' => $forecast['list'][$i]['main']['humidity'],
						'wind' => array(
							'speed' => $forecast['list'][$i]['wind']['speed'],
							'desc' => $this->windDegToString($forecast['list'][$i]['wind']['deg'])
						)
					);
				}
			}
		}

		if (isset($cityDatas['coord'])) {
			if ($apiMetarKey) {
				$coord = "lat/".$cityDatas['coord']['lat']."/lon/".$cityDatas['coord']['lon'];
				$metar = json_decode(file_get_contents(WeatherController::$apiCheckWXURL.$coord."/decoded?x-api-key=".$apiMetarKey), true);
				if (isset($metar['data']))
					$cityDatas['METAR'] = $metar['data'][0];
			}
			$airPollution = json_decode(file_get_contents(WeatherController::$apiAirPollutionURL."lat=".$cityDatas['coord']['lat']."&lon=".$cityDatas['coord']['lon']."&appid=".$apiKey), true);
			if (isset($airPollution['list']))
				$cityDatas['AIR'] = $airPollution['list'][0];
		}

		return $cityDatas;
	}

	private function windDegToString($deg) {

		if ($deg > 0 && $deg < 23 ||
			$deg > 333) {
			return $this->trans->t('North');
		}
		else if ($deg > 22 && $deg < 67) {
			return $this->trans->t('North-East');
		}
		else if ($deg > 66 && $deg < 113) {
			return $this->trans->t('East');
		}
		else if ($deg > 112 && $deg < 157) {
			return $this->trans->t('South-East');
		}
		else if ($deg > 156 && $deg < 201) {
			return $this->trans->t('South');
		}
		else if ($deg > 200 && $deg < 245) {
			return $this->trans->t('South-West');
		}
		else if ($deg > 244 && $deg < 289) {
			return $this->trans->t('West');
		}
		else if ($deg > 288 && $deg < 334) {
			return $this->trans->t('North-West');
		}
	}

	private function StrTimeToString($strtime) {
		$unixtime = strtotime($strtime);
		return $this->UnixTimeToString($unixtime);
	}

	private function UnixTimeToString($unixtime) {
		if (date("l", $unixtime) == "Monday") {
			return $this->trans->t('Monday') . " " . date("d-m H:i",$unixtime);
		}
		else if (date("l", $unixtime) == "Tuesday") {
			return $this->trans->t('Tuesday') . " " . date("d-m H:i",$unixtime);
		}
		else if (date("l", $unixtime) == "Wednesday") {
			return $this->trans->t('Wednesday') . " " . date("d-m H:i",$unixtime);
		}
		else if (date("l", $unixtime) == "Thursday") {
			return $this->trans->t('Thursday') . " " . date("d-m H:i",$unixtime);
		}
		else if (date("l", $unixtime) == "Friday") {
			return $this->trans->t('Friday') . " " . date("d-m H:i",$unixtime);
		}
		else if (date("l", $unixtime) == "Saturday") {
			return $this->trans->t('Saturday') . " " . date("d-m H:i",$unixtime);
		}
		else if (date("l", $unixtime) == "Sunday") {
			return $this->trans->t('Sunday') . " " . date("d-m H:i",$unixtime);
		}
	}

	private function isDay($unixtimesunrisen, $unixtimesunset) {
		$unixnowtime = strtotime("now");
		if (($unixnowtime > $unixtimesunrise) && ($unixnowtime < $unixtimesunset)) {
			return true;
		}
		return false;
	}
};

?>
