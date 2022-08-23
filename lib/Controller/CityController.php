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
use \OCP\AppFramework\Http\StrictContentSecurityPolicy;

use \OCA\Weather\Db\CityEntity;
use \OCA\Weather\Db\CityMapper;
use \OCA\Weather\Db\SettingsMapper;
use \OCA\Weather\Controller\IntermediateController;


class CityController extends IntermediateController {

	private $userId;
	private $mapper;
	private $settingsMapper;
	private $config;

	public function __construct ($appName, IConfig $config, IRequest $request, $userId, CityMapper $mapper, SettingsMapper $settingsMapper) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->mapper = $mapper;
		$this->settingsMapper = $settingsMapper;
		$this->config = $config;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index () {
		$response = new TemplateResponse($this->appName, 'main');  // templates/main.php

		$csp = new StrictContentSecurityPolicy();
		$csp->allowEvalScript();
		$csp->allowInlineStyle();

		$response->setContentSecurityPolicy($csp);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$cities = $this->mapper->getAll($this->userId);
		$home = $this->settingsMapper->getHome($this->userId);
		return new JSONResponse(array(
			"cities" => $cities,
			"userid" => $this->userId,
			"home" => $home
		));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function add ($name) {
		if (!$name) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		// Trim city name to remove unneeded spaces
		$name = trim($name);

		$cities = $this->mapper->getAll($this->userId);
		for ($i = 0; $i < count($cities); $i++) {
			if (strtolower($cities[$i]['name']) == strtolower($name)) {
				return new JSONResponse(array(), 409);
			}
		}

		$cityInfos = $this->getCityInformations($name);

		if (!$cityInfos["response"]) {
			return new JSONResponse($cityInfos, $cityInfos["code"]);
		}

		if ($id = $this->mapper->create($this->userId, $name)) {
			// Load parameter is set to true if we don't found previous cities.
			// This permit to trigger loading of the first city in UI
			return new JSONResponse(array(
				"id" => $id,
				"load" => count($cities) == 0)
			);
		}

		return new JSONResponse(array());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function delete ($id) {
		if (!$id || !is_numeric($id)) {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$city = $this->mapper->load($id);
		if ($city['user_id'] != $this->userId) {
			return new JSONResponse(array(), 403);
		}

		$entity = new CityEntity();
		$entity->setId($id);
		$entity->setUser_id($this->userId);

		$this->mapper->delete($entity);

		return new JSONResponse(array("deleted" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getNameFromGeo($lat, $lon) {
		$city_name = null;
		$opts = array(
			'http'=>array(
				'method'=>"GET",
				'header' =>
					"User-agent: NextcloudWeather\r\n".
					"Accept: */*\r\n".
					"Accept-language: en\r\n".
					"Connection: close\r\n",
			)
		);
		$city_info = json_decode(file_get_contents("https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=14&lat=".$lat."&lon=".$lon, false, stream_context_create($opts)), true);
		if ((isset($city_info['osm_type'])) && (isset($city_info['osm_id']))) {
			$osm_types = ['node' => 'N', 'relation' => 'R', 'way' => 'W'];
			$city_detail = json_decode(file_get_contents("https://nominatim.openstreetmap.org/details.php?osmtype=".$osm_types[$city_info['osm_type']]."&osmid=".$city_info['osm_id']."&addressdetails=1&hierarchy=0&group_hierarchy=1&format=json", false, stream_context_create($opts)), true);
			if (isset($city_detail['city_name']['names']['name:en'])) {
				$city_name = urlencode($city_detail['city_name']['names']['name:en']);
				if (isset($city_detail['city_name']['addresstags']['state'])) {
					$city_name = $city_name . "," . urlencode($city_detail['city_name']['addresstags']['state']);
				} else if (isset($city_info['address']['state'])) {
					$city_name = $city_name . "," . urlencode($city_info['address']['state']);
				}
			}
		}
		if (!$city_name) {
			if (isset($city_info['address']['suburb'])) {
				$city_name = urlencode($city_info['address']['suburb']);
			} else if (isset($city_info['address']['city_district'])) {
				$city_name = urlencode($city_info['address']['city_district']);
			} else if (isset($city_info['address']['town'])) {
				$city_name = urlencode($city_info['address']['town']);
			} else if (isset($city_info['address']['village'])) {
				$city_name = urlencode($city_info['address']['village']);
			} else if (isset($city_info['address']['city'])) {
				$city_name = urlencode($city_info['address']['city']);
			}
			if (isset($city_info['address']['county'])) {
				if ($city_name) {
					$city_name = $city_name . "," . urlencode($city_info['address']['county']);
				} else {
					$city_name = urlencode($city_info['address']['county']);
				}
			} else if (isset($city_info['address']['state'])) {
				if ($city_name) {
					$city_name = $city_name . "," . urlencode($city_info['address']['state']);
				} else {
					$city_name = urlencode($city_info['address']['state']);
				}
			}
		}

		if (isset($city_info['address']['country_code'])) {
			$countryList = array('AF' => 'Afghanistan','AX' => 'Aland Islands','AL' => 'Albania','DZ' => 'Algeria','AS' => 'American Samoa','AD' => 'Andorra','AO' => 'Angola','AI' => 'Anguilla','AQ' => 'Antarctica','AG' => 'Antigua and Barbuda','AR' => 'Argentina','AM' => 'Armenia','AW' => 'Aruba','AU' => 'Australia','AT' => 'Austria','AZ' => 'Azerbaijan','BS' => 'Bahamas the','BH' => 'Bahrain','BD' => 'Bangladesh','BB' => 'Barbados','BY' => 'Belarus','BE' => 'Belgium','BZ' => 'Belize','BJ' => 'Benin','BM' => 'Bermuda','BT' => 'Bhutan','BO' => 'Bolivia','BA' => 'Bosnia and Herzegovina','BW' => 'Botswana','BV' => 'Bouvet Island (Bouvetoya)','BR' => 'Brazil','IO' => 'British Indian Ocean Territory (Chagos Archipelago)','VG' => 'British Virgin Islands','BN' => 'Brunei Darussalam','BG' => 'Bulgaria','BF' => 'Burkina Faso','BI' => 'Burundi','KH' => 'Cambodia','CM' => 'Cameroon','CA' => 'Canada','CV' => 'Cape Verde','KY' => 'Cayman Islands','CF' => 'Central African Republic','TD' => 'Chad','CL' => 'Chile','CN' => 'China','CX' => 'Christmas Island','CC' => 'Cocos (Keeling) Islands','CO' => 'Colombia','KM' => 'Comoros the','CD' => 'Congo','CG' => 'Congo the','CK' => 'Cook Islands','CR' => 'Costa Rica','CI' => 'Cote d\'Ivoire','HR' => 'Croatia','CU' => 'Cuba','CY' => 'Cyprus','CZ' => 'Czech Republic','DK' => 'Denmark','DJ' => 'Djibouti','DM' => 'Dominica','DO' => 'Dominican Republic','EC' => 'Ecuador','EG' => 'Egypt','SV' => 'El Salvador','GQ' => 'Equatorial Guinea','ER' => 'Eritrea','EE' => 'Estonia','ET' => 'Ethiopia','FO' => 'Faroe Islands','FK' => 'Falkland Islands (Malvinas)','FJ' => 'Fiji the Fiji Islands','FI' => 'Finland','FR' => 'France','GF' => 'French Guiana','PF' => 'French Polynesia','TF' => 'French Southern Territories','GA' => 'Gabon','GM' => 'Gambia the','GE' => 'Georgia','DE' => 'Germany','GH' => 'Ghana','GI' => 'Gibraltar','GR' => 'Greece','GL' => 'Greenland','GD' => 'Grenada','GP' => 'Guadeloupe','GU' => 'Guam','GT' => 'Guatemala','GG' => 'Guernsey','GN' => 'Guinea','GW' => 'Guinea-Bissau','GY' => 'Guyana','HT' => 'Haiti','HM' => 'Heard Island and McDonald Islands','VA' => 'Holy See (Vatican City State)','HN' => 'Honduras','HK' => 'Hong Kong','HU' => 'Hungary','IS' => 'Iceland','IN' => 'India','ID' => 'Indonesia','IR' => 'Iran','IQ' => 'Iraq','IE' => 'Ireland','IM' => 'Isle of Man','IT' => 'Italy','JM' => 'Jamaica','JP' => 'Japan','JE' => 'Jersey','JO' => 'Jordan','KZ' => 'Kazakhstan','KE' => 'Kenya','KI' => 'Kiribati','KP' => 'Korea','KR' => 'Korea','KW' => 'Kuwait','KG' => 'Kyrgyz Republic','LA' => 'Lao','LV' => 'Latvia','LB' => 'Lebanon','LS' => 'Lesotho','LR' => 'Liberia','LY' => 'Libyan Arab Jamahiriya','LI' => 'Liechtenstein','LT' => 'Lithuania','LU' => 'Luxembourg','MO' => 'Macao','MK' => 'Macedonia','MG' => 'Madagascar','MW' => 'Malawi','MY' => 'Malaysia','MV' => 'Maldives','ML' => 'Mali','MT' => 'Malta','MH' => 'Marshall Islands','MQ' => 'Martinique','MR' => 'Mauritania','MU' => 'Mauritius','YT' => 'Mayotte','MX' => 'Mexico','FM' => 'Micronesia','MD' => 'Moldova','MC' => 'Monaco','MN' => 'Mongolia','ME' => 'Montenegro','MS' => 'Montserrat','MA' => 'Morocco','MZ' => 'Mozambique','MM' => 'Myanmar','NA' => 'Namibia','NR' => 'Nauru','NP' => 'Nepal','AN' => 'Netherlands Antilles','NL' => 'Netherlands the','NC' => 'New Caledonia','NZ' => 'New Zealand','NI' => 'Nicaragua','NE' => 'Niger','NG' => 'Nigeria','NU' => 'Niue','NF' => 'Norfolk Island','MP' => 'Northern Mariana Islands','NO' => 'Norway','OM' => 'Oman','PK' => 'Pakistan','PW' => 'Palau','PS' => 'Palestinian Territory','PA' => 'Panama','PG' => 'Papua New Guinea','PY' => 'Paraguay','PE' => 'Peru','PH' => 'Philippines','PN' => 'Pitcairn Islands','PL' => 'Poland','PT' => 'Portugal, Portuguese Republic','PR' => 'Puerto Rico','QA' => 'Qatar','RE' => 'Reunion','RO' => 'Romania','RU' => 'Russian Federation','RW' => 'Rwanda','BL' => 'Saint Barthelemy','SH' => 'Saint Helena','KN' => 'Saint Kitts and Nevis','LC' => 'Saint Lucia','MF' => 'Saint Martin','PM' => 'Saint Pierre and Miquelon','VC' => 'Saint Vincent and the Grenadines','WS' => 'Samoa','SM' => 'San Marino','ST' => 'Sao Tome and Principe','SA' => 'Saudi Arabia','SN' => 'Senegal','RS' => 'Serbia','SC' => 'Seychelles','SL' => 'Sierra Leone','SG' => 'Singapore','SK' => 'Slovakia (Slovak Republic)','SI' => 'Slovenia','SB' => 'Solomon Islands','SO' => 'Somalia, Somali Republic','ZA' => 'South Africa','GS' => 'South Georgia and the South Sandwich Islands','ES' => 'Spain','LK' => 'Sri Lanka','SD' => 'Sudan','SR' => 'Suriname','SJ' => 'Svalbard & Jan Mayen Islands','SZ' => 'Swaziland','SE' => 'Sweden','CH' => 'Switzerland, Swiss Confederation','SY' => 'Syrian Arab Republic','TW' => 'Taiwan','TJ' => 'Tajikistan','TZ' => 'Tanzania','TH' => 'Thailand','TL' => 'Timor-Leste','TG' => 'Togo','TK' => 'Tokelau','TO' => 'Tonga','TT' => 'Trinidad and Tobago','TN' => 'Tunisia','TR' => 'Turkey','TM' => 'Turkmenistan','TC' => 'Turks and Caicos Islands','TV' => 'Tuvalu','UG' => 'Uganda','UA' => 'Ukraine','AE' => 'United Arab Emirates','GB' => 'United Kingdom','US' => 'United States of America','UM' => 'United States Minor Outlying Islands','VI' => 'United States Virgin Islands','UY' => 'Uruguay, Eastern Republic of','UZ' => 'Uzbekistan','VU' => 'Vanuatu','VE' => 'Venezuela','VN' => 'Vietnam','WF' => 'Wallis and Futuna','EH' => 'Western Sahara','YE' => 'Yemen','ZM' => 'Zambia','ZW' => 'Zimbabwe');
			if ($city_name) {
				$city_name = $city_name . "," . urlencode($countryList[strtoupper($city_info['address']['country_code'])]);
			} else {
				$city_name = urlencode($countryList[strtoupper($city_info['address']['country_code'])]);
			}
		}
		return new JSONResponse(array('city_name' => $city_name));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getNameFromIp() {
		$ip = $_SERVER['REMOTE_ADDR'];
		$city_name = null;
		$opts = array(
			'http'=>array(
				'method'=>"GET",
				'header' =>
					"User-agent: NextcloudWeather\r\n".
					"Accept: */*\r\n".
					"Accept-language: en\r\n".
					"Connection: close\r\n",
			)
		);
		$city_info = json_decode(file_get_contents("http://ip-api.com/json/".$ip, false, stream_context_create($opts)), true);
		if (isset($city_info['city'])) {
			$city_name = urlencode($city_info['city']);
		}
		if (isset($city_info['country'])) {
			$city_name = $city_name . "," . urlencode($city_info['country']);
		}
		//return $this->getNameFromGeo($city_info['lat'], $city_info['lon']);
		return new JSONResponse(array('city_name' => $city_name));
	}

	private function getCityInformations ($name) {
		$apiKey = $this->config->getAppValue($this->appName, 'openweathermap_api_key');
		$cityDatas = json_decode($this->curlGET(
			"http://api.openweathermap.org/data/2.5/forecast?q=".urlencode($name)."&mode=json&APPID=".urlencode($apiKey))[1],
			true);

		// If no cod we just return a 502 as the API is not responding properly
		if (!array_key_exists('cod', $cityDatas)) {
			return array("code" => 502, "response" => null);
		}
		
		if ($cityDatas['cod'] != '200') {
			return array("code" => $cityDatas['cod'], "response" =>  null, "apikey" => $apiKey);
		}

		return array("code" => 200, "response" => $cityDatas);
	}
};
?>
