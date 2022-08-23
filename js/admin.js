/*
 * Copyright (c) 2017
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	if (!OCA.Weather) {
		/**
		 * Namespace for the files app
		 * @namespace OCA.Weather
		 */
		OCA.Weather = {};
	}

	/**
	 * @namespace OCA.Weather.Admin
	 */
	OCA.Weather.Admin = {
		initialize: function() {
			$('#submitOWMApiKey').on('click', _.bind(this._onClickSubmitOWMApiKey, this));
			$('#submitVCApiKey').on('click', _.bind(this._onClickSubmitVCApiKey, this));
			$('#submitWBApiKey').on('click', _.bind(this._onClickSubmitWBApiKey, this));
			$('#submitCWXApiKey').on('click', _.bind(this._onClickSubmitCWXApiKey, this));
			$('#submitSGApiKey').on('click', _.bind(this._onClickSubmitSGApiKey, this));
		},

		_onClickSubmitOWMApiKey: function () {
			OC.msg.startSaving('#OWMApiKeySettingsMsg');

			var request = $.ajax({
				url: OC.generateUrl('/apps/weather/settings/owmapikey'),
				type: 'POST',
				data: {
					owmapikey: $('#openweathermap-api-key').val()
				}
			});

			request.done(function (data) {
				$('#openweathermap-api-key').val(data.owmapikey);
				OC.msg.finishedSuccess('#OWMApiKeySettingsMsg', 'Saved');
			});

			request.fail(function () {
				OC.msg.finishedError('#OWMApiKeySettingsMsg', 'Error');
			});
		},

		_onClickSubmitVCApiKey: function () {
			OC.msg.startSaving('#VCApiKeySettingsMsg');

			var request = $.ajax({
				url: OC.generateUrl('/apps/weather/settings/vcapikey'),
				type: 'POST',
				data: {
					vcapikey: $('#visualcrossing-api-key').val()
				}
			});

			request.done(function (data) {
				$('#visualcrossing-api-key').val(data.vcapikey);
				OC.msg.finishedSuccess('#VCApiKeySettingsMsg', 'Saved');
			});

			request.fail(function () {
				OC.msg.finishedError('#VCApiKeySettingsMsg', 'Error');
			});
		},

		_onClickSubmitWBApiKey: function () {
			OC.msg.startSaving('#WBApiKeySettingsMsg');

			var request = $.ajax({
				url: OC.generateUrl('/apps/weather/settings/wbapikey'),
				type: 'POST',
				data: {
					wbapikey: $('#weatherbit-api-key').val()
				}
			});

			request.done(function (data) {
				$('#weatherbit-api-key').val(data.wbapikey);
				OC.msg.finishedSuccess('#WBApiKeySettingsMsg', 'Saved');
			});

			request.fail(function () {
				OC.msg.finishedError('#WBApiKeySettingsMsg', 'Error');
			});
		},

		_onClickSubmitCWXApiKey: function () {
			OC.msg.startSaving('#CWXApiKeySettingsMsg');

			var request = $.ajax({
				url: OC.generateUrl('/apps/weather/settings/checkwxapikey'),
				type: 'POST',
				data: {
					checkwxapikey: $('#checkwx-api-key').val()
				}
			});

			request.done(function (data) {
				$('#checkwx-api-key').val(data.checkwxapikey);
				OC.msg.finishedSuccess('#CWXApiKeySettingsMsg', 'Saved');
			});

			request.fail(function () {
				OC.msg.finishedError('#CWXApiKeySettingsMsg', 'Error');
			});
		},

		_onClickSubmitSGApiKey: function () {
			OC.msg.startSaving('#SGApiKeySettingsMsg');

			var request = $.ajax({
				url: OC.generateUrl('/apps/weather/settings/sgapikey'),
				type: 'POST',
				data: {
					sgapikey: $('#stormglass-api-key').val()
				}
			});

			request.done(function (data) {
				$('#stormglass-api-key').val(data.sgapikey);
				OC.msg.finishedSuccess('#SGApiKeySettingsMsg', 'Saved');
			});

			request.fail(function () {
				OC.msg.finishedError('#SGApiKeySettingsMsg', 'Error');
			});
		}

	}
})();

window.addEventListener('DOMContentLoaded', function () {
	OCA.Weather.Admin.initialize();
});
