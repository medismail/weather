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
use \OCP\ICacheFactory;
use \OCP\ICache;
use \OCP\IL10N;

use \OCA\Weather\Db\CityMapper;
use \OCA\Weather\Db\SettingsMapper;

use \OCA\Weather\Controller\IntermediateController;
use DateTime;
use DateTimezone;

class WeatherController extends IntermediateController {

	private $userId;
	private $mapper;
	private $settingsMapper;
	private $metric;
	private $provider;
	private $config;
	/** @var ICache */
	private $cache;
	private $trans;
	private static $apiWeatherURL = "http://api.openweathermap.org/data/2.5/weather?mode=json&q=";
	private static $apiForecastURL = "http://api.openweathermap.org/data/2.5/forecast?mode=json&q=";
	private static $apiAirPollutionURL = "http://api.openweathermap.org/data/2.5/air_pollution?mode=json&";
	private static $apiVisualCrossingURL = "https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/";
	private static $apiWeatherBitURL = "http://api.weatherbit.io/v2.0/current?city=";
	private static $apiWeatherBitForecastURL = "http://api.weatherbit.io/v2.0/forecast/daily?city=";
	private static $apiCheckWXURL = "https://api.checkwx.com/metar/";
	private static $apiStormGlassURL = "https://api.stormglass.io/v2/weather/point?";

	public function __construct ($appName, IConfig $config, IRequest $request, $userId, CityMapper $mapper, SettingsMapper $settingsMapper, ICacheFactory $cacheFactory, IL10N $trans) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->mapper = $mapper;
		$this->settingsMapper = $settingsMapper;
		$this->metric = $settingsMapper->getMetric($this->userId);
		$this->provider = $settingsMapper->getWeatherProvider($this->userId);
		$this->config = $config;
		$this->cache = $cacheFactory->createDistributed('weather');
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

		$apiOwmKey = $this->config->getAppValue($this->appName, 'openweathermap_api_key');
		$apiMetarKey = $this->config->getAppValue($this->appName, 'checkwx_api_key');
		$apiVCKey = $this->config->getAppValue($this->appName, 'visualcrossing_api_key');
		$apiWBKey = $this->config->getAppValue($this->appName, 'weatherbit_api_key');
		$apiSGKey = $this->config->getAppValue($this->appName, 'stormglass_api_key');

		$name = preg_replace("[ ]",'%20',$name);

		$currentLang = \OC::$server->getL10N('core')->getLanguageCode();
		if (preg_match("/_/i", $currentLang)) {
			$currentLang = strstr($currentLang, '_', true);
		}

		if ($this->provider == "openweathermap") {
			$openWeatherMapLang = array("ar", "bg", "ca", "cz", "de", "el", "en", "fa", "fi", "fr", "gl", "hr", "hu", "it", "ja", "kr", "la", "lt", "mk", "nl", "pl", "pt", "ro", "ru", "se", "sk", "sl", "es", "tr", "ua", "vi");

			if (in_array($currentLang, $openWeatherMapLang)) {
				$reqContent = $this->curlGET(WeatherController::$apiWeatherURL.$name."&APPID=".$apiOwmKey."&units=".$this->metric."&lang=".$currentLang);
			}
			else {
				$reqContent = $this->curlGET(WeatherController::$apiWeatherURL.$name."&APPID=".$apiOwmKey."&units=".$this->metric);
			}

			if ($reqContent[0] != Http::STATUS_OK) {
				$this->errorCode = $reqContent[0];
				return null;
			}

			$cityDatas = json_decode($reqContent[1], true);
			$cityDatas["forecast"] = array();

			if (in_array($currentLang, $openWeatherMapLang)) {
				$forecast = json_decode(file_get_contents(WeatherController::$apiForecastURL.$name."&APPID=".$apiOwmKey."&units=".$this->metric."&lang=".$currentLang), true);
			}
			else {
				$forecast = json_decode(file_get_contents(WeatherController::$apiForecastURL.$name."&APPID=".$apiOwmKey."&units=".$this->metric), true);
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
		} else if ($this->provider == "visualcrossing") {
			$visualCrossingLang = array("de", "en", "es", "fi", "fr", "it", "ja", "ko", "pt", "ru", "zh");
			$visualCrossingElements = "&elements=temp,feelslike,tempmin,tempmax,pressure,humidity,uvindex,dew,solarradiation,solarenergy,cloudcover,windspeed,winddir,conditions,description,sunsetEpoch,sunriseEpoch,,moonriseEpoch,moonsetEpoch,datetime,precip,icon,moonphase,visibility,snow";
			if (in_array($currentLang, $visualCrossingLang)) {
				$reqContent = $this->curlGET(WeatherController::$apiVisualCrossingURL.$name."?key=".$apiVCKey."&unitGroup=".$this->mapMetric()."&lang=".$currentLang."&iconSet=icons2".$visualCrossingElements);
			} else {
				$reqContent = $this->curlGET(WeatherController::$apiVisualCrossingURL.$name."?key=".$apiVCKey."&unitGroup=".$this->mapMetric()."&iconSet=icons2".$visualCrossingElements);
			}
			if ($reqContent[0] != Http::STATUS_OK) {
				$this->errorCode = $reqContent[0];
				return null;
			}
			$cityDatas = [];
			$forecast = json_decode($reqContent[1], true);
			//$forecast = json_decode(file_get_contents(WeatherController::$apiVisualCrossingURL.$name."?key=".$apiVCKey."&unitGroup=".$this->metric), true);
			$conditionCode =  array('snow' => 'Snow','snow-showers-day' => 'Snow','snow-showers-night' => 'Snow','thunder-rain' => 'Thunderstorm','thunder-showers-day' => 'Thunderstorm','thunder-showers-night' => 'Thunderstorm','rain' => 'Drizzle','showers-day' => 'Rain','showers-night' => 'Rain','fog' => 'Fog','wind' => 'Squall','cloudy' => 'Clouds','partly-cloudy-day' => 'Clouds','partly-cloudy-night' => 'Clouds','clear-day' => 'Clear','clear-night' => 'Clear');

			if (isset($forecast['currentConditions'])) {
				$cityDatas['main'] = array();
				$cityDatas['main']['temp'] = $forecast['currentConditions']['temp'];
				$cityDatas['main']['feels_like'] = $forecast['currentConditions']['feelslike'];
				$cityDatas['main']['temp_min'] = $forecast['days'][0]['tempmin'];
				$cityDatas['main']['temp_max'] = $forecast['days'][0]['tempmax'];
				$cityDatas['main']['pressure'] = $forecast['currentConditions']['pressure'];
				$cityDatas['main']['humidity'] = $forecast['currentConditions']['humidity'];
				$cityDatas['main']['uvindex'] = $forecast['currentConditions']['uvindex'];
				$cityDatas['main']['dew'] = $forecast['currentConditions']['dew'];
				$cityDatas['main']['solarradiation'] = $forecast['currentConditions']['solarradiation'];
				$cityDatas['main']['solarenergy'] = $forecast['currentConditions']['solarenergy'] ? $forecast['currentConditions']['solarenergy'] : 0;
				$cityDatas['main']['cloudcover'] = $forecast['currentConditions']['cloudcover'];
				$cityDatas['wind'] = array();
				$cityDatas['wind']['speed'] = $forecast['currentConditions']['windspeed'];
				$cityDatas['wind']['deg'] = $forecast['currentConditions']['winddir'];
				$cityDatas['weather'][0]['main'] = $conditionCode[($forecast['currentConditions']['icon'])]; //$this->mapCondition($forecast['currentConditions']['conditions']);
				$cityDatas['weather'][0]['description'] = $forecast['description'];
				$cityDatas['sys'] = array();
				$cityDatas['sys']['sunrise'] = $forecast['currentConditions']['sunriseEpoch'];
				$cityDatas['sys']['sunset'] = $forecast['currentConditions']['sunsetEpoch'];
				$cityDatas['name'] = $forecast['resolvedAddress'];
				$cityDatas['rain'] = $forecast['currentConditions']['precip'];
				$cityDatas['visibility'] = $forecast['currentConditions']['visibility']*1000;
				$cityDatas['sys']['moonrise'] = $forecast['days'][0]['moonriseEpoch'];
				$cityDatas['sys']['moonset'] = $forecast['days'][0]['moonsetEpoch'];
				$cityDatas['coord'] = array();
				$cityDatas['coord']['lat'] = $forecast['latitude'];
				$cityDatas['coord']['lon'] = $forecast['longitude'];
			}
			if (isset($forecast['days'])) {
				$cityDatas['forecast'] = array();
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
		} else if ($this->provider == "weatherbit") {
			$weatherBitLang = array("en", "ar", "az", "be", "bg", "bs", "ca", "cz", "da", "de", "fi", "fr", "el", "es", "et", "ja", "hr", "hu", "id", "it", "is", "iw", "kw", "lt", "nb", "nl", "pl", "pt", "ro", "ru", "sk", "sl", "sr", "sv", "tr", "uk", "zh");

			$countryList = array('Afghanistan' => 'AF','Aland Islands' => 'AX','Albania' => 'AL','Algeria' => 'DZ','American Samoa' => 'AS','Andorra' => 'AD','Angola' => 'AO','Anguilla' => 'AI','Antarctica' => 'AQ','Antigua and Barbuda' => 'AG','Argentina' => 'AR','Armenia' => 'AM','Aruba' => 'AW','Australia' => 'AU','Austria' => 'AT','Azerbaijan' => 'AZ','Bahamas the' => 'BS','Bahrain' => 'BH','Bangladesh' => 'BD','Barbados' => 'BB','Belarus' => 'BY','Belgium' => 'BE','Belize' => 'BZ','Benin' => 'BJ','Bermuda' => 'BM','Bhutan' => 'BT','Bolivia' => 'BO','Bosnia and Herzegovina' => 'BA','Botswana' => 'BW','Bouvet Island (Bouvetoya)' => 'BV','Brazil' => 'BR','British Indian Ocean Territory (Chagos Archipelago)' => 'IO','British Virgin Islands' => 'VG','Brunei Darussalam' => 'BN','Bulgaria' => 'BG','Burkina Faso' => 'BF','Burundi' => 'BI','Cambodia' => 'KH','Cameroon' => 'CM','Canada' => 'CA','Cape Verde' => 'CV','Cayman Islands' => 'KY','Central African Republic' => 'CF','Chad' => 'TD','Chile' => 'CL','China' => 'CN','Christmas Island' => 'CX','Cocos (Keeling) Islands' => 'CC','Colombia' => 'CO','Comoros the' => 'KM','Congo' => 'CD','Congo the' => 'CG','Cook Islands' => 'CK','Costa Rica' => 'CR','Cote d\'Ivoire' => 'CI', 'Croatia' => 'HR','Cuba' => 'CU','Cyprus' => 'CY','Czech Republic' => 'CZ','Denmark' => 'DK','Djibouti' => 'DJ','Dominica' => 'DM','Dominican Republic' => 'DO','Ecuador' => 'EC','Egypt' => 'EG','El Salvador' => 'SV','Equatorial Guinea' => 'GQ','Eritrea' => 'ER','Estonia' => 'EE','Ethiopia' => 'ET','Faroe Islands' => 'FO','Falkland Islands (Malvinas)' => 'FK','Fiji the Fiji Islands' => 'FJ','Finland' => 'FI','France' => 'FR','French Guiana' => 'GF','French Polynesia' => 'PF','French Southern Territories' => 'TF','Gabon' => 'GA','Gambia the' => 'GM','Georgia' => 'GE','Germany' => 'DE','Ghana' => 'GH','Gibraltar' => 'GI','Greece' => 'GR','Greenland' => 'GL','Grenada' => 'GD','Guadeloupe' => 'GP','Guam' => 'GU','Guatemala' => 'GT','Guernsey' => 'GG','Guinea' => 'GN','Guinea-Bissau' => 'GW','Guyana' => 'GY','Haiti' => 'HT','Heard Island and McDonald Islands' => 'HM','Holy See (Vatican City State)' => 'VA','Honduras' => 'HN','Hong Kong' => 'HK','Hungary' => 'HU','Iceland' => 'IS','India' => 'IN','Indonesia' => 'ID','Iran' => 'IR','Iraq' => 'IQ','Ireland' => 'IE','Isle of Man' => 'IM','Italy' => 'IT','Jamaica' => 'JM','Japan' => 'JP','Jersey' => 'JE','Jordan' => 'JO','Kazakhstan' => 'KZ','Kenya' => 'KE','Kiribati' => 'KI','Korea' => 'KP','Korea' => 'KR','Kuwait' => 'KW','Kyrgyz Republic' => 'KG','Lao' => 'LA','Latvia' => 'LV','Lebanon' => 'LB','Lesotho' => 'LS','Liberia' => 'LR','Libyan Arab Jamahiriya' => 'LY','Liechtenstein' => 'LI','Lithuania' => 'LT','Luxembourg' => 'LU','Macao' => 'MO','Macedonia' => 'MK','Madagascar' => 'MG','Malawi' => 'MW','Malaysia' => 'MY','Maldives' => 'MV','Mali' => 'ML','Malta' => 'MT','Marshall Islands' => 'MH','Martinique' => 'MQ','Mauritania' => 'MR','Mauritius' => 'MU','Mayotte' => 'YT','Mexico' => 'MX','Micronesia' => 'FM','Moldova' => 'MD','Monaco' => 'MC','Mongolia' => 'MN','Montenegro' => 'ME','Montserrat' => 'MS','Morocco' => 'MA','Mozambique' => 'MZ','Myanmar' => 'MM','Namibia' => 'NA','Nauru' => 'NR','Nepal' => 'NP','Netherlands Antilles' => 'AN','Netherlands the' => 'NL','New Caledonia' => 'NC','New Zealand' => 'NZ','Nicaragua' => 'NI','Niger' => 'NE','Nigeria' => 'NG','Niue' => 'NU','Norfolk Island' => 'NF','Northern Mariana Islands' => 'MP','Norway' => 'NO','Oman' => 'OM','Pakistan' => 'PK','Palau' => 'PW','Palestinian Territory' => 'PS','Panama' => 'PA','Papua New Guinea' => 'PG','Paraguay' => 'PY','Peru' => 'PE','Philippines' => 'PH','Pitcairn Islands' => 'PN','Poland' => 'PL','Portugal, Portuguese Republic' => 'PT','Puerto Rico' => 'PR','Qatar' => 'QA','Reunion' => 'RE','Romania' => 'RO','Russian Federation' => 'RU','Rwanda' => 'RW','Saint Barthelemy' => 'BL','Saint Helena' => 'SH','Saint Kitts and Nevis' => 'KN','Saint Lucia' => 'LC','Saint Martin' => 'MF','Saint Pierre and Miquelon' => 'PM','Saint Vincent and the Grenadines' => 'VC','Samoa' => 'WS','San Marino' => 'SM','Sao Tome and Principe' => 'ST','Saudi Arabia' => 'SA','Senegal' => 'SN','Serbia' => 'RS','Seychelles' => 'SC','Sierra Leone' => 'SL','Singapore' => 'SG','Slovakia (Slovak Republic)' => 'SK','Slovenia' => 'SI','Solomon Islands' => 'SB','Somalia, Somali Republic' => 'SO','South Africa' => 'ZA','South Georgia and the South Sandwich Islands' => 'GS','Spain' => 'ES','Sri Lanka' => 'LK','Sudan' => 'SD','Suriname' => 'SR','Svalbard & Jan Mayen Islands' => 'SJ','Swaziland' => 'SZ','Sweden' => 'SE','Switzerland, Swiss Confederation' => 'CH','Syrian Arab Republic' => 'SY','Taiwan' => 'TW','Tajikistan' => 'TJ','Tanzania' => 'TZ','Thailand' => 'TH','Timor-Leste' => 'TL','Togo' => 'TG','Tokelau' => 'TK','Tonga' => 'TO','Trinidad and Tobago' => 'TT','Tunisia' => 'TN','Turkey' => 'TR','Turkmenistan' => 'TM','Turks and Caicos Islands' => 'TC','Tuvalu' => 'TV','Uganda' => 'UG','Ukraine' => 'UA','United Arab Emirates' => 'AE','United Kingdom' => 'GB','United States of America' => 'US','United States Minor Outlying Islands' => 'UM','United States Virgin Islands' => 'VI','Uruguay, Eastern Republic of' => 'UY','Uzbekistan' => 'UZ','Vanuatu' => 'VU','Venezuela' => 'VE','Vietnam' => 'VN','Wallis and Futuna' => 'WF','Western Sahara' => 'EH','Yemen' => 'YE','Zambia' => 'ZM','Zimbabwe' => 'ZW');
			$names = \explode(",", $name);
			$cname = \urldecode(\end($names));
			if (\array_key_exists($cname, $countryList)) {
				$name = \str_replace(\end($names), $countryList[$cname], $name);
			}

			if (in_array($currentLang, $weatherBitLang)) {
				$reqContent = $this->curlGET(WeatherController::$apiWeatherBitURL.$name."&key=".$apiWBKey."&units=".$this->mapMetric()."&lang=".$currentLang);
			} else {
				$reqContent = $this->curlGET(WeatherController::$apiWeatherBitURL.$name."&key=".$apiWBKey."&units=".$this->mapMetric());
			}
			if ($reqContent[0] != Http::STATUS_OK) {
				$this->errorCode = $reqContent[0];
				return null;
			}
			$cityDatas = json_decode($reqContent[1], true);
			$conditionCode = ['200' => 'Thunderstorm', '201' => 'Thunderstorm', '202' => 'Thunderstorm', '230' => 'Thunderstorm', '231' => 'Thunderstorm', '233' => 'Thunderstorm', '300' => 'Drizzle', '301' => 'Drizzle', '302' => 'Drizzle', '500' => 'Rain', '501' =>' Rain', '502' => 'Rain', '511' => 'Rain', '900' => 'Rain', '600' => 'Snow', '601' => 'Snow', '602' => 'Snow', '610' => 'Snow', '611' => 'Snow', '612' => 'Snow', '621' => 'Snow', '622' => 'Snow', '623' => 'Snow', '700' => 'Mist', '711' => 'Smoke', '721' => 'Haze', '731' => 'Sand', '741' => 'Fog', '751' => 'Fog', '800' => 'Clear', '801' => 'Clouds', '802' => 'Clouds', '803' => 'Clouds', '804' => 'Clouds'];

			if (isset($cityDatas['data'])) {
				$cityDatas['main'] = array();
				$cityDatas['main']['temp'] = $cityDatas['data'][0]['temp'];
				$cityDatas['main']['feels_like'] = $cityDatas['data'][0]['app_temp'];
				$cityDatas['main']['pressure'] = $cityDatas['data'][0]['pres'];
				$cityDatas['main']['humidity'] = $cityDatas['data'][0]['rh'];
				$cityDatas['main']['uvindex'] = round($cityDatas['data'][0]['uv'], 2);
				$cityDatas['main']['dew'] = $cityDatas['data'][0]['dewpt'];
				$cityDatas['main']['solarradiation'] = $cityDatas['data'][0]['solar_rad'];
				$cityDatas['main']['solarenergy'] = round($cityDatas['data'][0]['solar_rad']*0.0036, 2);
				$cityDatas['main']['cloudcover'] = $cityDatas['data'][0]['clouds'];
				$cityDatas['wind'] = array();
				$cityDatas['wind']['speed'] = $cityDatas['data'][0]['wind_spd'];
				$cityDatas['wind']['deg'] = $cityDatas['data'][0]['wind_dir'];
				$cityDatas['weather'][0]['main'] = $conditionCode[($cityDatas['data'][0]['weather']['code'])];
				$cityDatas['weather'][0]['description'] = $cityDatas['data'][0]['weather']['description'];
				$cityDatas['sys'] = array();
				//$cityDatas['sys']['sunrise'] = $cityDatas['data'][0]['sunrise'];
				//$cityDatas['sys']['sunset'] = $cityDatas['data'][0]['sunset'];
				$cityDatas['sys']['country'] = $cityDatas['data'][0]['country_code'];
				$cityDatas['name'] = $cityDatas['data'][0]['city_name'];
				$cityDatas['rain'] = $cityDatas['data'][0]['precip'];
				$cityDatas['visibility'] = $cityDatas['data'][0]['vis']*1000;
				$cityDatas['aqi'] = $cityDatas['data'][0]['aqi'];
				$cityDatas['coord'] = array();
				$cityDatas['coord']['lat'] = $cityDatas['data'][0]['lat'];
				$cityDatas['coord']['lon'] = $cityDatas['data'][0]['lon'];
				unset($cityDatas['data']);
			}

			if (in_array($currentLang, $weatherBitLang)) {
				$forecast = json_decode(file_get_contents(WeatherController::$apiWeatherBitForecastURL.$name."&key=".$apiWBKey."&units".$this->mapMetric()."&lang=".$currentLang), true);
			} else {
				$forecast = json_decode(file_get_contents(WeatherController::$apiWeatherBitForecastURL.$name."&key=".$apiWBKey."&units".$this->mapMetric()), true);
			}

			if (isset($forecast['data'])) {
				$cityDatas['main']['temp_min'] = $forecast['data'][0]['min_temp'];
				$cityDatas['main']['temp_max'] = $forecast['data'][0]['max_temp'];
				$cityDatas['sys']['sunrise'] = $forecast['data'][0]['sunrise_ts'];
				$cityDatas['sys']['sunset'] = $forecast['data'][0]['sunset_ts'];
				$cityDatas['sys']['moonrise'] = $forecast['data'][0]['moonrise_ts'];
				$cityDatas['sys']['moonset'] = $forecast['data'][0]['moonset_ts'];
				$cityDatas['forecast'] = array();
				$maxFC = count($forecast['data']);
				for ($i = 0; $i < $maxFC; $i++) {
					$cityDatas['forecast'][] = array(
						'date' => $this->StrTimeToString($forecast['data'][$i]['valid_date']),
						'weather' => $forecast['data'][$i]['weather']['description'],
						'temperature' => $forecast['data'][$i]['temp'],
						'temperature_feelslike' => $forecast['data'][$i]['app_max_temp'],
						'temperature_min' => $forecast['data'][$i]['min_temp'],
						'temperature_max' => $forecast['data'][$i]['max_temp'],
						'precipitation' => $forecast['data'][$i]['precip'],
						'pressure' => $forecast['data'][$i]['pres'],
						'humidity' => $forecast['data'][$i]['rh'],
						'uvindex' => $forecast['data'][$i]['uv'],
						'wind' => array(
							'speed' => $forecast['data'][$i]['wind_spd'],
							'desc' => $this->windDegToString($forecast['data'][$i]['wind_dir'])
						)
					);
				}
			}
		}

		if (isset($cityDatas['coord'])) {
			if ($apiMetarKey) {
				$metar = $this->getMETAR($cityDatas['coord']['lat'], $cityDatas['coord']['lon'], $apiMetarKey);
				if (isset($metar['data']))
					$cityDatas['METAR'] = $metar['data'][0];
			}
			if ($apiSGKey) {
				$maritime = $this->getMaritimeData($name, $cityDatas['coord']['lat'], $cityDatas['coord']['lon'], $apiSGKey);
				if (isset($maritime['hours'])) {
					$now = new DateTime("now", new DateTimezone('UTC'));
					$h = $now->format('G');
					$cityDatas['Maritime']['waterTemperature'] = $maritime['hours'][$h]['waterTemperature']['sg'];
					$cityDatas['Maritime']['waveDirection'] = $maritime['hours'][$h]['waveDirection']['sg'];
					$cityDatas['Maritime']['waveHeight'] = $maritime['hours'][$h]['waveHeight']['sg'];
					$cityDatas['Maritime']['wavePeriod'] = $maritime['hours'][$h]['wavePeriod']['sg'];
					$cityDatas['Maritime']['swellDirection'] = $maritime['hours'][$h]['swellDirection']['sg'];
					$cityDatas['Maritime']['swellHeight'] = $maritime['hours'][$h]['swellHeight']['sg'];
					$cityDatas['Maritime']['swellPeriod'] = $maritime['hours'][$h]['swellPeriod']['sg'];
					$cityDatas['Maritime']['secondarySwellDirection'] = $maritime['hours'][$h]['secondarySwellDirection']['sg'];
					$cityDatas['Maritime']['secondarySwellHeight'] = $maritime['hours'][$h]['secondarySwellHeight']['sg'];
					$cityDatas['Maritime']['secondarySwellPeriod'] = $maritime['hours'][$h]['secondarySwellPeriod']['sg'];
					$cityDatas['Maritime']['windWaveDirection'] = $maritime['hours'][$h]['windWaveDirection']['sg'];
					$cityDatas['Maritime']['windWaveHeight'] = $maritime['hours'][$h]['windWaveHeight']['sg'];
					$cityDatas['Maritime']['windWavePeriod'] = $maritime['hours'][$h]['windWavePeriod']['sg'];
				}
			}
			$airPollution = json_decode(file_get_contents(WeatherController::$apiAirPollutionURL."lat=".$cityDatas['coord']['lat']."&lon=".$cityDatas['coord']['lon']."&appid=".$apiOwmKey), true);
			if (isset($airPollution['list']))
				$cityDatas['AIR'] = $airPollution['list'][0];
		}

		return $cityDatas;
	}

	private function windDegToString($deg) {

		if ($deg >= 0 && $deg < 23 ||
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

	private function isDay($unixtimesunrise, $unixtimesunset) {
		$unixnowtime = strtotime("now");
		if (($unixnowtime > $unixtimesunrise) && ($unixnowtime < $unixtimesunset)) {
			return true;
		}
		return false;
	}

	private function mapMetric() {
		if ($this->provider == "visualcrossing") {
			if ($this->metric == "kelvin") {
				return "base";
			} else if ($this->metric == "imperial") {
				return "us";
			}
		}
		else if($this->provider == "weatherbit") {
			if ($this->metric == "metric") {
				return "M";
			} else if ($this->metric == "kelvin") {
				return "K";
			} else if ($this->metric == "imperial") {
				return "F";
			}
		}
		return $this->metric;
	}

	private function getMETAR($lat, $lon, $apiMetarKey) {
		$cacheKey = WeatherController::$apiCheckWXURL . '|' . $lat . '|' . $lon;
		$cacheValue = $this->cache->get($cacheKey);
		if ($cacheValue !== null) {
			return $cacheValue;
		}

		try {
			$coord = "lat/".$lat."/lon/".$lon;
			$metar = json_decode(file_get_contents(WeatherController::$apiCheckWXURL.$coord."/decoded?x-api-key=".$apiMetarKey), true);
			if (isset($metar['data'])) {
				// default cache duration is 10 minutes
				$cacheDuration = 60 * 10;
				$this->cache->set($cacheKey, $metar, $cacheDuration);
			}
			return $metar;
		} catch (\Exception $e) {
			return ['error' => $e->getMessage()];
		}
	}

	private function getMaritimeData($name, $lat, $lon, $apiSGKey) {
		$cacheKey = WeatherController::$apiStormGlassURL . '|' . $name;
		$cacheValue = $this->cache->get($cacheKey);
		if ($cacheValue !== null) {
			return $cacheValue;
		}

		try {
			$params = "&params=airTemperature,pressure,cloudCover,currentDirection,currentSpeed,gust,humidity,iceCover,precipitation,snowDepth,swellDirection,swellHeight,swellPeriod,secondarySwellPeriod,secondarySwellDirection,secondarySwellHeight,visibility,waterTemperature,waveDirection,waveHeight,wavePeriod,windWaveDirection,windWaveHeight,windWavePeriod,windDirection,windSpeed";
			$start = new DateTime('today', new DateTimezone('UTC'));
			$end = new DateTime('today +1 day', new DateTimezone('UTC'));
			$opts = array(
				'http'=>array(
					'method'=>"GET",
					'header' =>
						"User-agent: NextcloudWeather\r\n".
						"Accept: */*\r\n".
						"Authorization: ".$apiSGKey."\r\n",
					'ignore_errors' => true
				)
			);
			$forecast = json_decode(file_get_contents(WeatherController::$apiStormGlassURL."lat=".$lat."&lng=".$lon.$params."&start=".$start->getTimestamp()."&end=".$end->getTimestamp(), false, stream_context_create($opts)), true);
			if (isset($forecast['hours'])) {
				// default cache duration is 12 hour
				$now = new DateTime('now', new DateTimezone('UTC'));
				$cacheDuration = $end->getTimestamp() - $now->getTimestamp(); //60 * 60 * (24-$now->format('H'));
				$this->cache->set($cacheKey, $forecast, $cacheDuration);
			}
			return $forecast;
		} catch (\Exception $e) {
			return ['error' => $e->getMessage()];
		}
	}

};

?>
