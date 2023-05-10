/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

/**
 * \file    js/dolisirh.js
 * \ingroup dolisirh
 * \brief   JavaScript file for module DoliSIRH.
 */

/* Javascript library of module DoliSIRH */

'use strict';
/**
 * @namespace EO_Framework_Init
 *
 * @author Evarisk <dev@evarisk.com>
 * @copyright 2015-20222 Evarisk
 */

if ( ! window.eoxiaJS ) {
	/**
	 * [eoxiaJS description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @type {Object}
	 */
	window.eoxiaJS = {};

	/**
	 * [scriptsLoaded description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @type {Boolean}
	 */
	window.eoxiaJS.scriptsLoaded = false;
}

if ( ! window.eoxiaJS.scriptsLoaded ) {
	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.init = function() {
		window.eoxiaJS.load_list_script();
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.load_list_script = function() {
		if ( ! window.eoxiaJS.scriptsLoaded) {
			var key = undefined, slug = undefined;
			for ( key in window.eoxiaJS ) {

				if ( window.eoxiaJS[key].init ) {
					window.eoxiaJS[key].init();
				}

				for ( slug in window.eoxiaJS[key] ) {

					if ( window.eoxiaJS[key] && window.eoxiaJS[key][slug] && window.eoxiaJS[key][slug].init ) {
						window.eoxiaJS[key][slug].init();
					}

				}
			}

			window.eoxiaJS.scriptsLoaded = true;
		}
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.refresh = function() {
		var key = undefined;
		var slug = undefined;
		for ( key in window.eoxiaJS ) {
			if ( window.eoxiaJS[key].refresh ) {
				window.eoxiaJS[key].refresh();
			}

			for ( slug in window.eoxiaJS[key] ) {

				if ( window.eoxiaJS[key] && window.eoxiaJS[key][slug] && window.eoxiaJS[key][slug].refresh ) {
					window.eoxiaJS[key][slug].refresh();
				}
			}
		}
	};

	$( document ).ready( window.eoxiaJS.init );
}

/**
 * @namespace EO_Framework_Loader
 *
 * @author Eoxia <dev@eoxia.com>
 * @copyright 2015-2018 Eoxia
 */

/*
 * Gestion du loader.
 *
 * @since 1.0.0
 * @version 1.0.0
 */
if ( ! window.eoxiaJS.loader ) {

	/**
	 * [loader description]
	 *
	 * @memberof EO_Framework_Loader
	 *
	 * @type {Object}
	 */
	window.eoxiaJS.loader = {};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Loader
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.loader.init = function() {
		window.eoxiaJS.loader.event();
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Loader
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.loader.event = function() {
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Loader
	 *
	 * @param  {void} element [description]
	 * @returns {void}         [description]
	 */
	window.eoxiaJS.loader.display = function( element ) {
		// Loader spécial pour les "button-progress".
		if ( element.hasClass( 'button-progress' ) ) {
			element.addClass( 'button-load' )
		} else {
			element.addClass( 'wpeo-loader' );
			var el = $( '<span class="loader-spin"></span>' );
			element[0].loaderElement = el;
			element.append( element[0].loaderElement );
		}
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Loader
	 *
	 * @param  {jQuery} element [description]
	 * @returns {void}         [description]
	 */
	window.eoxiaJS.loader.remove = function( element ) {
		if ( 0 < element.length && ! element.hasClass( 'button-progress' ) ) {
			element.removeClass( 'wpeo-loader' );

			$( element[0].loaderElement ).remove();
		}
	};
}

/**
 * Initialise l'objet "modal" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.4.0
 * @version 1.4.0
 */
window.eoxiaJS.modal = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.modal.init = function() {
	window.eoxiaJS.modal.event();
};

/**
 * La méthode contenant tous les événements pour la modal.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.modal.event = function() {
	$( document ).on( 'click', '.modal-close', window.eoxiaJS.modal.closeModal );
	$( document ).on( 'click', '.modal-open', window.eoxiaJS.modal.openModal );
	$( document ).on( 'click', '.modal-refresh', window.eoxiaJS.modal.refreshModal );
};

/**
 * Open Modal.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param  {MouseEvent} event Les attributs lors du clic.
 * @return {void}
 */
window.eoxiaJS.modal.openModal = function ( event ) {
	let idSelected = $(this).attr('value');
	if (document.URL.match(/#/)) {
		var urlWithoutTag = document.URL.split(/#/)[0]
	} else {
		var urlWithoutTag = document.URL
	}
	history.pushState({ path:  document.URL}, '', urlWithoutTag);

	// Open modal signature.
	if ($(this).hasClass('modal-signature-open')) {
		$('#modal-signature' + idSelected).addClass('modal-active');
		window.eoxiaJS.signature.modalSignatureOpened( $(this) );
	}

	// Open modal timespent.
	if ($(this).hasClass('timespent')) {
		let taskID = $(this).attr('data-task-id');
		let timestamp = $(this).attr('data-timestamp');
		let cell = $(this).attr('data-cell');
		let date = $(this).attr('data-date');
		$('.timespent-taskid').val(taskID);
		$('.timespent-timestamp').val(timestamp);
		$('.timespent-cell').val(cell);
		$('.timespent-create').attr('value', taskID);
		$('.timespent-date').html(date);
		$('#timespent').addClass('modal-active');
	}

	$('.notice').addClass('hidden');
};

/**
 * Close Modal.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param  {MouseEvent} event Les attributs lors du clic.
 * @return {void}
 */
window.eoxiaJS.modal.closeModal = function ( event ) {
	$(this).closest('.modal-active').removeClass('modal-active')
	$('.clicked-photo').attr('style', '');
	$('.clicked-photo').removeClass('clicked-photo');
	$('.notice').addClass('hidden');
};

/**
 * Refresh Modal.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param  {MouseEvent} event Les attributs lors du clic.
 * @return {void}
 */
window.eoxiaJS.modal.refreshModal = function ( event ) {
	window.location.reload();
};

/**
 * [dropdown description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @type {Object}
 */
window.eoxiaJS.dropdown = {};

/**
 * [description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @returns {void} [description]
 */
window.eoxiaJS.dropdown.init = function() {
	window.eoxiaJS.dropdown.event();
};

/**
 * [description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @returns {void} [description]
 */
window.eoxiaJS.dropdown.event = function() {
	$( document ).on( 'keyup', window.eoxiaJS.dropdown.keyup );
	$( document ).on( 'click', '.wpeo-dropdown:not(.dropdown-active) .dropdown-toggle:not(.disabled)', window.eoxiaJS.dropdown.open );
	$( document ).on( 'click', '.wpeo-dropdown.dropdown-active .dropdown-content', function(e) { e.stopPropagation() } );
	$( document ).on( 'click', '.wpeo-dropdown.dropdown-active:not(.dropdown-force-display) .dropdown-content .dropdown-item', window.eoxiaJS.dropdown.close  );
	$( document ).on( 'click', '.wpeo-dropdown.dropdown-active', function ( e ) { window.eoxiaJS.dropdown.close( e ); e.stopPropagation(); } );
	$( document ).on( 'click', 'body', window.eoxiaJS.dropdown.close );
};

/**
 * [description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @param  {void} event [description]
 * @returns {void}       [description]
 */
window.eoxiaJS.dropdown.keyup = function( event ) {
	if ( 27 === event.keyCode ) {
		window.eoxiaJS.dropdown.close();
	}
}

/**
 * [description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @param  {void} event [description]
 * @returns {void}       [description]
 */
window.eoxiaJS.dropdown.open = function( event ) {
	var triggeredElement = $( this );
	var angleElement = triggeredElement.find('[data-fa-i2svg]');
	var callbackData = {};
	var key = undefined;

	window.eoxiaJS.dropdown.close( event, $( this ) );

	if ( triggeredElement.attr( 'data-action' ) ) {
		window.eoxiaJS.loader.display( triggeredElement );

		triggeredElement.get_data( function( data ) {
			for ( key in callbackData ) {
				if ( ! data[key] ) {
					data[key] = callbackData[key];
				}
			}

			window.eoxiaJS.request.send( triggeredElement, data, function( element, response ) {
				triggeredElement.closest( '.wpeo-dropdown' ).find( '.dropdown-content' ).html( response.data.view );

				triggeredElement.closest( '.wpeo-dropdown' ).addClass( 'dropdown-active' );

				/* Toggle Button Icon */
				if ( angleElement ) {
					window.eoxiaJS.dropdown.toggleAngleClass( angleElement );
				}
			} );
		} );
	} else {
		triggeredElement.closest( '.wpeo-dropdown' ).addClass( 'dropdown-active' );

		/* Toggle Button Icon */
		if ( angleElement ) {
			window.eoxiaJS.dropdown.toggleAngleClass( angleElement );
		}
	}

	event.stopPropagation();
};

/**
 * [description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @param  {void} event [description]
 * @returns {void}       [description]
 */
window.eoxiaJS.dropdown.close = function( event ) {
	var _element = $( this );
	$( '.wpeo-dropdown.dropdown-active:not(.no-close)' ).each( function() {
		var toggle = $( this );
		var triggerObj = {
			close: true
		};

		_element.trigger( 'dropdown-before-close', [ toggle, _element, triggerObj ] );

		if ( triggerObj.close ) {
			toggle.removeClass( 'dropdown-active' );

			/* Toggle Button Icon */
			var angleElement = $( this ).find('.dropdown-toggle').find('[data-fa-i2svg]');
			if ( angleElement ) {
				window.eoxiaJS.dropdown.toggleAngleClass( angleElement );
			}
		} else {
			return;
		}
	});
};

/**
 * [description]
 *
 * @memberof EO_Framework_Dropdown
 *
 * @param  {jQuery} button [description]
 * @returns {void}        [description]
 */
window.eoxiaJS.dropdown.toggleAngleClass = function( button ) {
	if ( button.hasClass('fa-caret-down') || button.hasClass('fa-caret-up') ) {
		button.toggleClass('fa-caret-down').toggleClass('fa-caret-up');
	}
	else if ( button.hasClass('fa-caret-circle-down') || button.hasClass('fa-caret-circle-up') ) {
		button.toggleClass('fa-caret-circle-down').toggleClass('fa-caret-circle-up');
	}
	else if ( button.hasClass('fa-angle-down') || button.hasClass('fa-angle-up') ) {
		button.toggleClass('fa-angle-down').toggleClass('fa-angle-up');
	}
	else if ( button.hasClass('fa-chevron-circle-down') || button.hasClass('fa-chevron-circle-up') ) {
		button.toggleClass('fa-chevron-circle-down').toggleClass('fa-chevron-circle-up');
	}
};

/**
 * @namespace EO_Framework_Tooltip
 *
 * @author Evarisk <dev@eoxia.com>
 * @copyright 2015-2018 Evarisk
 */

if ( ! window.eoxiaJS.tooltip ) {

	/**
	 * [tooltip description]
	 *
	 * @memberof EO_Framework_Tooltip
	 *
	 * @type {Object}
	 */
	window.eoxiaJS.tooltip = {};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Tooltip
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.tooltip.init = function() {
		window.eoxiaJS.tooltip.event();
	};

	window.eoxiaJS.tooltip.tabChanged = function() {
		$( '.wpeo-tooltip' ).remove();
	}

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Tooltip
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.tooltip.event = function() {
		$( document ).on( 'mouseenter touchstart', '.wpeo-tooltip-event:not([data-tooltip-persist="true"])', window.eoxiaJS.tooltip.onEnter );
		$( document ).on( 'mouseleave touchend', '.wpeo-tooltip-event:not([data-tooltip-persist="true"])', window.eoxiaJS.tooltip.onOut );
	};

	window.eoxiaJS.tooltip.onEnter = function( event ) {
		window.eoxiaJS.tooltip.display( $( this ) );
	};

	window.eoxiaJS.tooltip.onOut = function( event ) {
		window.eoxiaJS.tooltip.remove( $( this ) );
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Tooltip
	 *
	 * @param  {void} event [description]
	 * @returns {void}       [description]
	 */
	window.eoxiaJS.tooltip.display = function( element ) {
		var direction = ( $( element ).data( 'direction' ) ) ? $( element ).data( 'direction' ) : 'top';
		var el = $( '<span class="wpeo-tooltip tooltip-' + direction + '">' + $( element ).attr( 'aria-label' ) + '</span>' );
		var pos = $( element ).position();
		var offset = $( element ).offset();
		$( element )[0].tooltipElement = el;
		$( 'body' ).append( $( element )[0].tooltipElement );

		if ( $( element ).data( 'color' ) ) {
			el.addClass( 'tooltip-' + $( element ).data( 'color' ) );
		}

		var top = 0;
		var left = 0;

		switch( $( element ).data( 'direction' ) ) {
			case 'left':
				top = ( offset.top - ( el.outerHeight() / 2 ) + ( $( element ).outerHeight() / 2 ) ) + 'px';
				left = ( offset.left - el.outerWidth() - 10 ) + 3 + 'px';
				break;
			case 'right':
				top = ( offset.top - ( el.outerHeight() / 2 ) + ( $( element ).outerHeight() / 2 ) ) + 'px';
				left = offset.left + $( element ).outerWidth() + 8 + 'px';
				break;
			case 'bottom':
				top = ( offset.top + $( element ).height() + 10 ) + 10 + 'px';
				left = ( offset.left - ( el.outerWidth() / 2 ) + ( $( element ).outerWidth() / 2 ) ) + 'px';
				break;
			case 'top':
				top = offset.top - el.outerHeight() - 4  + 'px';
				left = ( offset.left - ( el.outerWidth() / 2 ) + ( $( element ).outerWidth() / 2 ) ) + 'px';
				break;
			default:
				top = offset.top - el.outerHeight() - 4  + 'px';
				left = ( offset.left - ( el.outerWidth() / 2 ) + ( $( element ).outerWidth() / 2 ) ) + 'px';
				break;
		}

		el.css( {
			'top': top,
			'left': left,
			'opacity': 1
		} );

		$( element ).on("remove", function() {
			$( $( element )[0].tooltipElement ).remove();

		} );
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Tooltip
	 *
	 * @param  {void} event [description]
	 * @returns {void}       [description]
	 */
	window.eoxiaJS.tooltip.remove = function( element ) {
		if ( $( element )[0] && $( element )[0].tooltipElement ) {
			$( $( element )[0].tooltipElement ).remove();
		}
	};
}

/**
 * Initialise l'objet "task" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
window.eoxiaJS.task = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.eoxiaJS.task.init = function() {
	window.eoxiaJS.task.event();
};

/**
 * La méthode contenant tous les événements pour le task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.eoxiaJS.task.event = function() {
	$( document ).on( 'click', '.auto-fill-timespent', window.eoxiaJS.task.addTimeSpent );
	$( document ).on( 'click', '.auto-fill-timespent-project', window.eoxiaJS.task.divideTimeSpent );
	$( document ).on( 'click', '.show-only-favorite-tasks', window.eoxiaJS.task.showOnlyFavoriteTasks );
	$( document ).on( 'click', '.show-only-tasks-with-timespent', window.eoxiaJS.task.showOnlyTasksWithTimeSpent );
	$( document ).on( 'click', '.timespent-create', window.eoxiaJS.task.createTimeSpent );
	$( document ).on( 'click', '.toggleTaskFavorite', window.eoxiaJS.task.toggleTaskFavorite );
};

/**
 * Remplit automatiquement le temps à pointer disponible sur une tâche
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {MouseEvent} event [description]
 * @return {void}
 */
window.eoxiaJS.task.addTimeSpent = function( event ) {
	let nonConsumedMinutes = $('.non-consumed-time-minute').val()
	let nonConsumedHours = $('.non-consumed-time-hour').val()
	$('.inputhour').val('')
	$('.inputminute').val('')
	$(this).closest('.duration').find('.inputhour').val(nonConsumedHours)
	$(this).closest('.duration').find('.inputminute').val(nonConsumedMinutes)
};

/**
 * Répartit automatiquement le temps à pointer disponible entre les tâches du projet
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {MouseEvent} event [description]
 * @return {void}
 */
window.eoxiaJS.task.divideTimeSpent = function( event ) {
	let projectId = $(this).closest('.project-line').attr('id')

	let taskMinute = 0
	let taskHour = 0

	let nonConsumedMinutes = $('.non-consumed-time-minute').val()
	let nonConsumedHours = $('.non-consumed-time-hour').val()
	let totalTimeInMinutes = +nonConsumedMinutes + +nonConsumedHours*60

	let taskLinkedCounter = $('.'+projectId).length
	let minutesToSpend = parseInt(totalTimeInMinutes/taskLinkedCounter)

	$('.inputhour').val('')
	$('.inputminute').val('')

	$('.'+projectId).each(function() {
		taskHour = parseInt(minutesToSpend/60)
		taskMinute = minutesToSpend%60

		$(this).find('.inputhour').val(taskHour)
		$(this).find('.inputminute').val(taskMinute)
	})
};

/**
 * Active/désactive la configuration pour n'afficher que les tâches favorites
 *
 * @since   1.0.0
 * @version 1.2.1
 *
 * @return {void}
 */
window.eoxiaJS.task.showOnlyFavoriteTasks = function() {
	let token = $('.id-container').find('input[name="token"]').val();
	let querySeparator = '?';

	document.URL.match(/\?/) ? querySeparator = '&' : 1

	let showOnlyFavoriteTasks;
	if ($(this).is(':checked')) {
		showOnlyFavoriteTasks = 1;
	} else {
		showOnlyFavoriteTasks = 0;
	}

	$.ajax({
		url: document.URL + querySeparator + "action=showOnlyFavoriteTasks&token=" + token,
		type: "POST",
		processData: false,
		data: JSON.stringify({
			showOnlyFavoriteTasks: showOnlyFavoriteTasks
		}),
		contentType: false,
		success: function() {
			window.location.reload();
		},
		error: function() {}
	});
};

/**
 * Active/désactive la configuration pour n'afficher que les tâches avec du temps pointé
 *
 * @since   1.1.0
 * @version 1.2.1
 *
 * @return {void}
 */
window.eoxiaJS.task.showOnlyTasksWithTimeSpent = function() {
	let token = $('.id-container').find('input[name="token"]').val();
	let querySeparator = '?';

	document.URL.match(/\?/) ? querySeparator = '&' : 1

	let showOnlyTasksWithTimeSpent;
	if ($(this).is(':checked')) {
		showOnlyTasksWithTimeSpent = 1;
	} else {
		showOnlyTasksWithTimeSpent = 0;
	}

	$.ajax({
		url: document.URL + querySeparator + "action=showOnlyTasksWithTimeSpent&token=" + token,
		type: "POST",
		processData: false,
		data: JSON.stringify({
			showOnlyTasksWithTimeSpent: showOnlyTasksWithTimeSpent
		}),
		contentType: false,
		success: function() {
			window.location.reload();
		},
		error: function() {}
	});
};

/**
 * Action create timespent.
 *
 * @since   1.1.1
 * @version 1.1.1
 *
 * @return {void}
 */
window.eoxiaJS.task.createTimeSpent = function ( event ) {
	let taskID  = $(this).attr('value');
	let element = $(this).closest('.timespent-add-modal').find('.timespent-container');
	let cell    = $('#tablelines3').find('tr[data-taskid=' + taskID + ']').find('td[data-cell=' + element.find('.timespent-cell').val() + ']')

	let timestamp = element.find('.timespent-timestamp').val();
	let datehour  = element.find('.timespent-datehour').val();
	let datemin   = element.find('.timespent-datemin').val();
	let comment   = element.find('.timespent-comment').val();
	let hour      = element.find('.timespent-hour').val();
	let min       = element.find('.timespent-min').val();

	window.eoxiaJS.loader.display($(this));
	window.eoxiaJS.loader.display(cell);

	let token = $('.fiche').find('input[name="token"]').val();
	let querySeparator = '?';

	document.URL.match(/\?/) ? querySeparator = '&' : 1

	$.ajax({
		url: document.URL + querySeparator + 'action=addTimeSpent&token=' + token,
		data: JSON.stringify({
			taskID: taskID,
			timestamp: timestamp,
			datehour: datehour,
			datemin: datemin,
			comment: comment,
			hour: hour,
			min: min
		}),
		type: "POST",
		processData: false,
		contentType: false,
		success: function ( resp ) {
			$('.loader-spin').remove();
			$('.wpeo-loader').removeClass('wpeo-loader')
			$('#timespent').removeClass('modal-active')
			$('#tablelines3').html($(resp).find('#tablelines3'))
		},
		error: function ( resp ) {
		}
	});
};

window.eoxiaJS.task.toggleTaskFavorite = function () {
	let taskID = $(this).attr('value');
	let token = $('form[name="addtime"]').find('input[name="token"]').val();
	let querySeparator = '?';

	document.URL.match(/\?/) ? querySeparator = '&' : 1

	$.ajax({
		url: document.URL + querySeparator + 'action=toggleTaskFavorite&taskId=' + taskID + '&token=' + token,
		type: "POST",
		processData: false,
		contentType: false,
		success: function () {
			let taskContainer = $('#' + taskID)

			if (taskContainer.hasClass('fas')) {
				taskContainer.removeClass('fas')
				taskContainer.addClass('far')
			} else if (taskContainer.hasClass('far')) {
				taskContainer.removeClass('far')
				taskContainer.addClass('fas')
			}
		},
		error: function (resp) {

		}
	});
}


/**
 * Initialise l'objet "menu" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
window.eoxiaJS.menu = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.eoxiaJS.menu.init = function() {
	window.eoxiaJS.menu.event();
};

/**
 * La méthode contenant tous les événements pour le migration.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.eoxiaJS.menu.event = function() {
	$(document).on( 'click', ' .blockvmenu', window.eoxiaJS.menu.toggleMenu);
	$(document).ready(function() { window.eoxiaJS.menu.setMenu()});
}

/**
 * Action Toggle main menu.
 *
 * @since   8.5.0
 * @version 9.4.0
 *
 * @return {void}
 */
window.eoxiaJS.menu.toggleMenu = function() {

	var menu = $(this).closest('#id-left').find('a.vmenu, font.vmenudisabled, span.vmenu, a.vsmenu');
	var elementParent = $(this).closest('#id-left').find('div.vmenu')
	var text = '';

	if ($(this).find('span.vmenu').find('.fa-chevron-circle-left').length > 0) {

		menu.each(function () {
			text = $(this).html().split('</i>');
			if (text[1].match(/&gt;/)) {
				text[1] = text[1].replace(/&gt;/, '')
			}
			$(this).attr('title', text[1])
			$(this).html(text[0]);
		});

		elementParent.css('width', '30px');
		elementParent.find('.blockvmenusearch').hide();
		$('span.vmenu').attr('title', ' Agrandir le menu')

		$('span.vmenu').html($('span.vmenu').html());

		$(this).find('span.vmenu').find('.fa-chevron-circle-left').removeClass('fa-chevron-circle-left').addClass('fa-chevron-circle-right');
		localStorage.setItem('maximized', 'false')

	} else if ($(this).find('span.vmenu').find('.fa-chevron-circle-right').length > 0) {

		menu.each(function () {
			$(this).html($(this).html().replace('&gt;','') + ' ' + $(this).attr('title'));
		});

		elementParent.css('width', '188px');
		elementParent.find('.blockvmenusearch').show();
		$('div.menu_titre').attr('style', 'width: 188px !important; cursor : pointer' )
		$('span.vmenu').attr('title', ' Réduire le menu')
		$('span.vmenu').html('<i class="fas fa-chevron-circle-left pictofixedwidth"></i> Réduire le menu');

		localStorage.setItem('maximized', 'true')

		$(this).find('span.vmenu').find('.fa-chevron-circle-right').removeClass('fa-chevron-circle-right').addClass('fa-chevron-circle-left');
	}
};

/**
 * Action set  menu.
 *
 * @since   8.5.0
 * @version 9.0.1
 *
 * @return {void}
 */
window.eoxiaJS.menu.setMenu = function() {
	if ($('.blockvmenu.blockvmenufirst').html().match(/dolisirh/)) {
		$('span.vmenu').find('.fa-chevron-circle-left').parent().parent().parent().attr('style', 'cursor:pointer ! important')

		if (localStorage.maximized == 'false') {
			$('#id-left').attr('style', 'display:none !important')
		}

		if (localStorage.maximized == 'false') {
			var text = '';
			var menu = $('#id-left').find('a.vmenu, font.vmenudisabled, span.vmenu, a.vsmenu');
			var elementParent = $(document).find('div.vmenu')

			menu.each(function () {
				text = $(this).html().split('</i>');
				$(this).attr('title', text[1])
				$(this).html(text[0]);
			});

			$('#id-left').attr('style', 'display:block !important')
			$('div.menu_titre').attr('style', 'width: 50px !important')
			$('span.vmenu').attr('title', ' Agrandir le menu')

			$('span.vmenu').html($('span.vmenu').html())
			$('span.vmenu').find('.fa-chevron-circle-left').removeClass('fa-chevron-circle-left').addClass('fa-chevron-circle-right');

			elementParent.css('width', '30px');
			elementParent.find('.blockvmenusearch').hide();
		}
		localStorage.setItem('currentString', '')
		localStorage.setItem('keypressNumber', 0)
	}
};

/**
 * Initialise l'objet "signature" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.4.0
 * @version 1.4.0
 */
window.eoxiaJS.signature = {};

/**
 * Initialise le canvas signature
 *
 * @since   1.4.0
 * @version 1.4.0
 */
window.eoxiaJS.signature.canvas;

/**
 * Initialise le boutton signature
 *
 * @since   1.4.0
 * @version 1.4.0
 */
window.eoxiaJS.signature.buttonSignature;

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.init = function() {
	window.eoxiaJS.signature.event();
};

/**
 * La méthode contenant tous les événements pour la signature.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.event = function() {
	$( document ).on( 'click', '.signature-erase', window.eoxiaJS.signature.clearCanvas );
	$( document ).on( 'click', '.signature-validate', window.eoxiaJS.signature.createSignature );
	$( document ).on( 'click', '.auto-download', window.eoxiaJS.signature.autoDownloadSpecimen );
};

/**
 * Open modal signature
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.modalSignatureOpened = function( triggeredElement ) {
	window.eoxiaJS.signature.buttonSignature = triggeredElement;

	var ratio =  Math.max( window.devicePixelRatio || 1, 1 );

	window.eoxiaJS.signature.canvas = document.querySelector('#modal-signature' + triggeredElement.attr('value') + ' canvas' );

	window.eoxiaJS.signature.canvas.signaturePad = new SignaturePad( window.eoxiaJS.signature.canvas, {
		penColor: "rgb(0, 0, 0)"
	} );

	window.eoxiaJS.signature.canvas.width = window.eoxiaJS.signature.canvas.offsetWidth * ratio;
	window.eoxiaJS.signature.canvas.height = window.eoxiaJS.signature.canvas.offsetHeight * ratio;
	window.eoxiaJS.signature.canvas.getContext( "2d" ).scale( ratio, ratio );
	window.eoxiaJS.signature.canvas.signaturePad.clear();

	var signature_data = $( '#signature_data' + triggeredElement.attr('value') ).val();
	window.eoxiaJS.signature.canvas.signaturePad.fromDataURL(signature_data);
};

/**
 * Action Clear sign
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.clearCanvas = function( event ) {
	var canvas = $( this ).closest( '.modal-signature' ).find( 'canvas' );
	canvas[0].signaturePad.clear();
};

/**
 * Action create signature
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.createSignature = function() {
	let elementSignatory = $(this).attr('value');
	let elementRedirect  = '';
	let elementCode = '';
	let elementZone  = $(this).find('#zone' + elementSignatory).attr('value');
	let elementConfCAPTCHA  = $('#confCAPTCHA').val();
	let actionContainerSuccess = $('.noticeSignatureSuccess');
	var signatoryIDPost = '';
	if (elementSignatory !== 0) {
		signatoryIDPost = '&signatoryID=' + elementSignatory;
	}

	if ( ! $(this).closest( '.wpeo-modal' ).find( 'canvas' )[0].signaturePad.isEmpty() ) {
		var signature = $(this).closest( '.wpeo-modal' ).find( 'canvas' )[0].toDataURL();
	}

	let token = $('.modal-signature').find('input[name="token"]').val();

	var url = '';
	var type = '';
	if (elementZone == "private") {
		url = document.URL + '&action=addSignature' + signatoryIDPost + '&token=' + token;
		type = "POST"
	} else {
		url = document.URL + '&action=addSignature' + signatoryIDPost + '&token=' + token;
		type = "POST";
	}

	if (elementConfCAPTCHA == 1) {
		elementCode = $('#securitycode').val();
		let elementSessionCode = $('#sessionCode').val();
		if (elementSessionCode != elementCode) {
			elementRedirect = $('#redirectSignatureError').val();
		}
	} else {
		elementRedirect = $(this).find('#redirect' + elementSignatory).attr('value');
	}

	$.ajax({
		url: url,
		type: type,
		processData: false,
		contentType: 'application/octet-stream',
		data: JSON.stringify({
			signature: signature,
			code: elementCode
		}),
		success: function( resp ) {
			if (elementZone == "private") {
				actionContainerSuccess.html($(resp).find('.noticeSignatureSuccess .all-notice-content'));
				actionContainerSuccess.removeClass('hidden');
				$('.signatures-container').html($(resp).find('.signatures-container'));
			} else {
				window.location.replace(elementRedirect);
			}
		},
		error: function ( ) {
			alert('Error')
		}
	});
};

/**
 * Download signature
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.download = function(fileUrl, filename) {
	var a = document.createElement("a");
	a.href = fileUrl;
	a.setAttribute("download", filename);
	a.click();
}

/**
 * Auto Download signature specimen
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.signature.autoDownloadSpecimen = function( event ) {
	let element = $(this).closest('.file-generation')
	let token = $('.digirisk-signature-container').find('input[name="token"]').val();
	let url = document.URL + '&action=builddoc&token=' + token
	$.ajax({
		url: url,
		type: "POST",
		success: function ( ) {
			let filename = element.find('.specimen-name').attr('value')
			let path = element.find('.specimen-path').attr('value')

			window.eoxiaJS.signature.download(path + filename, filename);
			$.ajax({
				url: document.URL + '&action=remove_file&token=' + token,
				type: "POST",
				success: function ( ) {
				},
				error: function ( ) {
				}
			});
		},
		error: function ( ) {
		}
	});
};

/**
 * Initialise l'objet "keyEvent" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.4.0
 * @version 1.4.0
 */
window.eoxiaJS.keyEvent = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.keyEvent.init = function() {
	window.eoxiaJS.keyEvent.event();
};

/**
 * La méthode contenant tous les événements pour le migration.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.eoxiaJS.keyEvent.event = function() {
	$( document ).on( 'keydown', window.eoxiaJS.keyEvent.keyup );
}

/**
 * Action modal close & validation with key events
 *
 * @since   1.2.0
 * @version 1.2.1
 *
 * @return {void}
 */
window.eoxiaJS.keyEvent.keyup = function(event) {
	if ('Escape' === event.key) {
		$(this).find('.modal-active .modal-close .fas.fa-times').first().click();
	}
	if (!$(event.target).is('input, textarea')) {
		if ('Enter' === event.key)  {
			$(this).find('.button_search').click();
		}
		if (event.shiftKey && 'Enter' === event.key)  {
			$(this).find('.button_removefilter').click();
		}
	}
	if ($(event.target).is('body')) {
		let height = '';
		if (event.shiftKey) {
			height = 200 + 'px';
			$('body').find('.wpeo-tooltip').css('height', height);
		}
	}
};

/**
 * Initialise l'objet "dashboard" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.1.1
 * @version 1.1.1
 */
window.eoxiaJS.dashboard = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.1.1
 * @version 1.1.1
 *
 * @return {void}
 */
window.eoxiaJS.dashboard.init = function() {
	window.eoxiaJS.dashboard.event();
};

/**
 * La méthode contenant tous les événements pour les dashboards.
 *
 * @since   1.1.1
 * @version 1.1.1
 *
 * @return {void}
 */
window.eoxiaJS.dashboard.event = function() {
	$( document ).on( 'change', '.add-dashboard-widget', window.eoxiaJS.dashboard.addDashBoardInfo );
	$( document ).on( 'click', '.close-dashboard-widget', window.eoxiaJS.dashboard.closeDashBoardInfo );
	$( document ).on( 'click', '.select-dataset-dashboard-info', window.eoxiaJS.dashboard.selectDatasetDashboardInfo );
};

/**
 * Add widget dashboard info
 *
 * @since   9.5.0
 * @version 9.5.0
 *
 * @return {void}
 */
window.eoxiaJS.dashboard.addDashBoardInfo = function() {
	var dashboardWidgetForm = document.getElementById('dashBoardForm');
	var formData = new FormData(dashboardWidgetForm);
	let dashboardWidgetName = formData.get('boxcombo')
	let querySeparator = '?';
	let token = $('.dashboard').find('input[name="token"]').val();
	document.URL.match(/\?/) ? querySeparator = '&' : 1

	$.ajax({
		url: document.URL + querySeparator + 'action=adddashboardinfo&token='+token,
		type: "POST",
		processData: false,
		data: JSON.stringify({
			dashboardWidgetName: dashboardWidgetName
		}),
		contentType: false,
		success: function ( resp ) {
			window.location.reload();
		},
		error: function ( ) {
		}
	});
};

/**
 * Close widget dashboard info
 *
 * @since   9.5.0
 * @version 9.5.0
 *
 * @return {void}
 */
window.eoxiaJS.dashboard.closeDashBoardInfo = function() {
	let box = $(this);
	let dashboardWidgetName = $(this).attr('data-widgetname');
	let querySeparator = '?';
	let token = $('.dashboard').find('input[name="token"]').val();
	document.URL.match(/\?/) ? querySeparator = '&' : 1

	$.ajax({
		url: document.URL + querySeparator + 'action=closedashboardinfo&token='+token,
		type: "POST",
		processData: false,
		data: JSON.stringify({
			dashboardWidgetName: dashboardWidgetName
		}),
		contentType: false,
		success: function ( resp ) {
			box.closest('.box-flex-item').fadeOut(400)
			$('.add-widget-box').attr('style', '')
			$('.add-widget-box').html($(resp).find('.add-widget-box').children())
		},
		error: function ( ) {
		}
	});
};

/**
 * Select dataset dashboard info.
 *
 * @since   1.2.1
 * @version 1.2.1
 *
 * @return {void}
 */
window.eoxiaJS.dashboard.selectDatasetDashboardInfo = function() {
	let userID = $('#search_userid').val();
	let year   = $('#search_year').val();
	let month  = $('#search_month').val();

	let querySeparator = '?';
	let token = $('.dashboard').find('input[name="token"]').val();
	document.URL.match(/\?/) ? querySeparator = '&' : 1

	window.eoxiaJS.loader.display($('.fichecenter'));

	$.ajax({
		url: document.URL + querySeparator + 'token=' + token + '&search_userid=' + userID + '&search_year=' + year + '&search_month=' + month,
		type: "POST",
		processData: false,
		contentType: false,
		success: function(resp) {
			$('.fichecenter').replaceWith($(resp).find('.fichecenter'));
		},
		error: function() {}
	});
};

/**
 * Initialise l'objet "form" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.1.0
 * @version 1.1.0
 */
window.eoxiaJS.form = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @return {void}
 */
window.eoxiaJS.form.init = function() {
	window.eoxiaJS.form.event();
};

/**
 * La méthode contenant tous les événements pour les buttons.
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @return {void}
 */
window.eoxiaJS.form.event = function() {
	$( document ).on( 'submit', '#addtimeform', window.eoxiaJS.form.searchForm );
};

window.eoxiaJS.form.searchForm = function(event) {
	event.preventDefault()

	var addTimeForm = document.getElementById('addtimeform');
	var formData = new FormData(addTimeForm);
	let newFormData = new FormData();

	for (const pair of formData.entries()) {
		if (pair[1] != '') {
			newFormData.append(pair[0], pair[1])
		}
	}
	window.eoxiaJS.loader.display($('#addtimeform'));

	$.ajax({
		url: document.URL,
		type: "POST",
		data: newFormData,
		processData: false,
		contentType: false,
		success: function (resp) {
			$('.wpeo-loader').removeClass('wpeo-loader');
			$('#addtimeform').html($(resp).find('#addtimeform').children())
		},
	});
}
