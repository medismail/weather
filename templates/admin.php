<?php

\OCP\Util::addScript('weather', 'admin');

/** @var $l \OCP\IL10N */
/** @var $_ array */

?>

<div id="weather" class="section">
	<h2><?php p($l->t('Weather')) ?></h2>
	<p>
		<label for="openweathermap-api-key"><?php p($l->t('OpenWeatherMap API Key')) ?></label>
		<br />
		<input id="openweathermap-api-key" type="text" value="<?php p($_['openweathermap_api_key']) ?>" />
		<input type="submit" id="submitOWMApiKey" value="<?php p($l->t('Save')); ?>"/>
	</p>
	<p>
		<label for="visualcrossing-api-key"><?php p($l->t('Visual Crossing API Key')) ?></label>
		<br />
		<input id="visualcrossing-api-key" type="text" value="<?php p($_['visualcrossing_api_key']) ?>" />
		<input type="submit" id="submitVCApiKey" value="<?php p($l->t('Save')); ?>"/>
	</p>
	<p>
		<label for="weatherbit-api-key"><?php p($l->t('WeatherBit API Key')) ?></label>
		<br />
		<input id="weatherbit-api-key" type="text" value="<?php p($_['weatherbit_api_key']) ?>" />
		<input type="submit" id="submitWBApiKey" value="<?php p($l->t('Save')); ?>"/>
	</p>
	<p>
		<label for="checkwx-api-key"><?php p($l->t('CheckWX API Key')) ?></label>
		<br />
		<input id="checkwx-api-key" type="text" value="<?php p($_['checkwx_api_key']) ?>" />
		<input type="submit" id="submitCWXApiKey" value="<?php p($l->t('Save')); ?>"/>
	</p>
</div>

