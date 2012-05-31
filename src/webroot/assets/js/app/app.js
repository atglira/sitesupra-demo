"use strict";

(function ($) {
	
$.app = {
	
	/**
	 * App options
	 * @type {Object}
	 * @private
	 */
	'options': {
		'namespace': 'attach'
	},
	
	/**
	 * List of instances
	 * @type {Object}
	 * @private
	 */
	'instances': {},
	
	/**
	 * List of pending injections
	 * @type {Object}
	 * @private
	 */
	'injections': {},
	
	/**
	 * Find and instantiate all modules inside element
	 * 
	 * @param {Object} element jQuery element, which should be parsed. All children are also parsed
	 * @return Array of instances
	 * @type {Array}
	 */
	'parse': function (element, options) {
		var instances	= [],
			instance	= null,
			elements	= null,
			i			= 0,
			ii			= 0;
		
		$.extend(this.options, options || {});
		
		if (element.data(this.options.namespace)) {
			instance = this.factory(element);
			if (instance) instances.push(instance);
		}
		
		//Children
		elements = element.find('[data-' + this.options.namespace + ']');
		ii = elements.length;
		
		for(; i<ii; i++) {
			instance = this.factory(elements.eq(i));
			if (instance) instances.push(instance);
		}
		
		//Trigger $.refresh plugin
		if ($.refresh && typeof $.refresh.trigger === 'function') {
			$.refresh.trigger('refresh', element);
		}
		
		return instances;
	},
	
	/**
	 * Destroy element and children element modules
	 * 
	 * @param {Object} element jQuery element, which children modules will be cleaned up
	 */
	'cleanup': function (element) {
		element.find('[data-id]').each(function () {
			$.app.destroy($(this));
		});
		
		//Trigger $.refresh plugin
		if ($.refresh && typeof $.refresh.trigger === 'function') {
			$.refresh.trigger('cleanup', element);
		}
	},
	
	/**
	 * Remove instance, clean up
	 * 
	 * @param {String} id Instance ID or element
	 */
	'destroy': function (id) {
		if (typeof id === 'object') {
			id = id.attr('data-id');
		}
		
		var instance	= this.instances[id],
			element		= null;
		
		if (instance) {
			if (instance.destroy) {
				instance.destroy();
			}
			if (instance.element) {
				instance.element.removeData('instance');
				
				//Destroy children module instances
				instance.element.find('[data-id]').each(function () {
					$.app.destroy($(this));
				});
			}
			
			delete(this.instances[id]);
		}
	},
	
	/**
	 * Instantiate module on element
	 * 
	 * @param {Object} element jQuery element, which should be parsed
	 * @param {Object} options Optional options with which module will be instantiated
	 * @return Module instance
	 * @type {Object}
	 * @private
	 */
	'factory': function (element, options) {
		var options	= options || {},
			attach	= options[this.options.namespace] || element.data(this.options.namespace) || '',
			id		= options.id || element.data('id') || element.attr('id') || ('app_' + (+new Date()) + String(~~(Math.random() * 9999))),
			module	= window,
			plugin	= false;
		
		options.id = id;
		options[this.options.namespace] = attach;
		
		if (element.data('instance')) {
			//Element has already module attached to it
			return null;
		}
		
		if (typeof attach === 'function') {
			module = attach;
		} else if (typeof attach === 'string' && attach) {
			if (attach.indexOf('$.fn.') == 0 || attach.indexOf('jQuery.fn.') == 0) {
				//jQuery plugin
				module = attach.replace(/^[^\.]\.fn\./i, '');
				plugin = true;
			} else {
				//Find module function
				$.each(attach.split('.'), function (index, part) {
					module = module[part];
					if (!module) return false;
				});
			}
		} else {
			return null;
		}
		
		//Instantiate
		if (typeof module === 'function' || (plugin && element[module])) {
			//Add data attribute values to the options
			options = $.extend({}, element.data(), options || {});
			
			if (plugin) {
				if (typeof element[module] === 'function') {
					module = element[module](options);
				}
			} else {
				module = module(element, options);
			}
			
			//jQuery plugins will return same element
			if (typeof module === 'object' && module !== element) {
				//Add data-id attribute to be able to clean up later
				element.attr('data-id', id);
				element.data('instance', module);
				
				this.instances[id] = module;
				return module;
			}
		}
		
		return null;
	},
	
	/**
	 * Inject additional properties and methods into module instance
	 * 
	 * @param {String} id Instance ID or element
	 * @param {Object} proto Properties and methods to inject 
	 */
	'inject': function (id, proto) {
		var instance = this.get(id);
		if (instance) {
			instance.inject(proto);
		}
		
		this.injections[id] = this.injections[id] || [];
		this.injections[id].push(proto);
	},
	
	/**
	 * Find single instance using ID
	 * 
	 * @param {String} id Instance ID or element
	 * @return Instance with given ID
	 * @type {Object}
	 */
	'get': function (id) {
		if (typeof id === 'object') {
			var instance = id.data('instance');
			if (instance) {
				return instance;
			} else {
				id = id.attr('data-id') || id.attr('id');
			}
		}
		
		return this.instances[id] || null;
	},
	
	/**
	 * Trigger method on instance
	 * 
	 * @param {String} id Instance ID or element
	 * @param {String} method Optional method name or function
	 * @param {Array} args Optional arguments for method
	 * @return Method return value
	 */
	'trigger': function (id, method, args) {
		var instance	= this.get(id),
			type		= $.type(method);
		
		if (!instance) {
			return;
		}
		
		if (type === 'array' && !args) {
			args = method;
			method = null;
		}
		
		if (type === 'string') {
			method = instance[method];
		} else if (type === 'function') {
			//Already a function, don't do anything
		} else {
			method = instance[instance.default_method];
		}
		
		if (typeof method === 'function') {
			args = $.isArray(args) ? args : [];
			return method.apply(instance, args);
		}
	},
	
	/**
	 * Create new module
	 * 
	 * @param {Object} extend Object to extend
	 * @param {Object} proto Module prototype
	 */
	'module': function (extend, proto) {
		var constr = function (element, options) {
			//Check if "new" keyword was used
			if (!(this instanceof constr)) {
				return new constr(element, options);
			}
			
			//While extending don't call constructor
			if ($.app.module.extending) return this;
			
			//Set 'proto' to for easier super calls
			this.proto = constr.prototype;
			
			//Inject pending protos $.app.inject(..., {...})
			if ($.app.injections[options.id]) {
				var injections	= $.app.injections[options.id],
					i			= 0,
					ii			= injections.length;
				
				for(; i<ii; i++) {
					this.inject(injections[i]);
				}
			}
			
			//It is possible to prevent module instance from beeing created
			//Used if module has specific requirements which weren't satisfied
			var instance = this.init(element, options);
			if (typeof instance === 'undefined') {
				//If nothing is returned assument everything was ok
				instance = this;
			}
			
			return instance;
		};
		
		if (!proto && typeof extend === 'object') {
			proto = extend;
			extend = null;
		}
		
		if (typeof extend === 'function') {
			$.app.module.extending = true;
			constr.prototype = new extend();
			$.app.module.extending = false;
		} else {
			//Extend with module prototype
			$.extend(constr.prototype, $.app.module.proto);
		}
		
		//Mixin proto
		$.extend(constr.prototype, proto || {});
		
		//Inject functionality
		constr.inject = function (proto) {
			$.extend(true, constr.prototype, proto);
		};
		
		return constr;
	}
	
};

//Module prototype
$.app.module.proto = {
	
	/**
	 * Element to which this module is attached to
	 * @type {Object}
	 */
	'element': null,
	
	/**
	 * Module configuration options
	 * @type {Object}
	 */
	'options': null,
	
	/**
	 * Default method for 'trigger'
	 * @type {String}
	 */
	'default_method': null,
	
	/**
	 * Initialize module
	 * 
	 * @param {Object} element
	 * @param {Object} options
	 * @constructor
	 */
	'init': function (element, options) {
		this.element = element;
		this.options = options || {};
	},
	
	/**
	 * Destructor
	 * 
	 * @private
	 */
	'destroy': function () {
		//Overwrite destroy to avoid infinite loop
		this.destroy = function () {};
		
		$.app.destroy(this.element);
		
		this.element = null;
		this.options = null;
	},
	
	/**
	 * Returns elements matching CSS selector inside element
	 * Helper function
	 * 
	 * @param {String} selector CSS selector
	 * @return jQuery object with elements matching CSS selector
	 * @type {Object}
	 */
	'find': function (selector) {
		return this.element ? this.element.find(selector) : $();
	},
	
	/**
	 * Find single child instance using ID
	 * 
	 * @param {String} id Instance ID or element
	 * @return Instance with given ID
	 * @type {Object}
	 */
	'get': function (id) {
		var instance = $.app.get(id);
		
		if (instance) {
			if (instance.element) {
				if (instance.element.closest(this.element)) {
					return instance;
				}
			} else {
				return instance;
			}
		}
		
		return null;
	},
	
	/**
	 * Returns proxy function with context of this module
	 * Helper function
	 * 
	 * @param {Function} fn Function which will be proxied
	 * @return New function which execution context is this module
	 * @type {Function}
	 */
	'proxy': function (fn) {
		return $.proxy(fn, this);
	},
	
	/**
	 * Inject properties and methods into this instance
	 * 
	 * @param {Object} proto Properties and methods which will be injected into instance
	 */
	'inject': function (proto) {
		$.extend(true, this, proto);
	}
};

})(jQuery);