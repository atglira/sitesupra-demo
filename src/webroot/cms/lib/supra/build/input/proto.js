//Invoke strict mode
"use strict";
	
YUI.add('supra.input-proto', function (Y) {
	function Input (config) {
		Input.superclass.constructor.apply(this, arguments);
		this.init.apply(this, arguments);
		
		this._original_value = null;
	}
	
	Input.NAME = 'input';
	Input.ATTRS = {
		'inputNode': {
			value: null
		},
		'labelNode': {
			value: null
		},
		'descriptionNode': {
			value: null
		},
		'value': {
			value: '',
			setter: '_setValue',
			getter: '_getValue'
		},
		'saveValue': {
			value: '',
			getter: '_getSaveValue'
		},
		'defaultValue': {
			value: null
		},
		'disabled': {
			value: null,
			setter: '_setDisabled'
		},
		'label': {
			value: null,
			setter: '_setLabel'
		},
		'description': {
			value: null,
			setter: '_setDescription'
		},
		'validationRules': {
			value: [],
			setter: '_processValidationRules'
		},
		'id': {
			value: null
		},
		'error': {
			value: false,
			setter: '_setError'
		},
		'style': {
			value: null,
			setter: '_setStyle'
		},
		
		// Parent widget, usually Supra.Form instance
		'parent': {
			value: null
		},
		// Root parent widget, usually Supra.Form instance
		'root': {
			value: null
		}
	};
	
	Input.HTML_PARSER = {
		'inputNode': function (srcNode) {
			var inp = srcNode;
			if (!srcNode.test('input,select,textarea')) {
				inp = srcNode.one('input') || srcNode.one('select') || srcNode.one('textarea');
			}
			
			this.set('inputNode', inp);
			return inp;
		},
		'labelNode': function (srcNode) {
			var label = this.get('labelNode');
			if (!label) {
				var label = srcNode.one('label');
				if (!label) {
					label = srcNode.previous();
					if (label && !label.test('label')) label = null;
				}
				this.set('labelNode', label);
			}
			return label;
		},
		'descriptionNode': function (srcNode) {
			var node = this.get('descriptionNode');
			if (!node) {
				node = srcNode.one('p');
				if (!node) {
					node = srcNode.next();
					if (node && !node.test('p')) node = null;
				}
			}
			if (node && node.test('p.label')) node = null;
			return node;
		},
		'disabled': function (srcNode) {
			var val = this.get('disabled');
			var inp = this.get('inputNode');
			
			if (inp) {
				if (val === null) {
					return inp.get('disabled');
				} else {
					this.set('disabled', val);
				}
			}
			
			return !!val;
		},
		'style': function (srcNode) {
			return srcNode.getAttribute('suStyle') || null;
		}
	};
	
	Y.extend(Input, Y.Widget, {
		INPUT_TEMPLATE: '<input type="text" value="" />',
		LABEL_TEMPLATE: '<label></label>',
		DESCRIPTION_TEMPLATE: '<p class="description"></p>',
		
		_original_value: null,
		
		bindUI: function () {
			Input.superclass.bindUI.apply(this, arguments);
			
			var input = this.get('inputNode');
			if (!input) return;
			
			//On Input focus, focus input element
			this.on('focusedChange', function (event) {
				if (event.newVal && event.newVal != event.prevVal) {
					this.get('inputNode').focus();
				}
			}, this);
			
			//On input change update value
			input.on('change', function (event) {
				if (!input.test('input[type="checkbox"],input[type="radio"]')) {
					this.set('value', input.get('value'));
				}
			}, this);
			
			//On input element blur, blur Input
			input.on('blur', this.blur, this);
		},
		
		renderUI: function () {
			Input.superclass.renderUI.apply(this, arguments);
			
			var inp = this.get('inputNode');
			var lbl = this.get('labelNode');
			var descr = this.get('descriptionNode');
			var cont = this.get('contentBox');
			var bound = this.get('boundingBox');
			
			if (!inp && this.INPUT_TEMPLATE) {
				inp = Y.Node.create(this.INPUT_TEMPLATE);
				cont.prepend(inp);
				this.set('inputNode', inp);
			}
			
			if (descr && inp) {
				descr.addClass('description');
				inp.insert(descr, 'after');
			}
			
			if (inp && !lbl && this.LABEL_TEMPLATE) {
				var id = inp.getAttribute('id');
				
				lbl = Y.Node.create(this.LABEL_TEMPLATE);
				lbl.setAttribute('for', id);
				lbl.set('text', this.get('label') || '');
				
				if (!this.get('label')) {
					lbl.addClass('hidden');
				}
				
				if (cont.compareTo(inp)) {
					inp.insert(lbl, 'before');
				} else {
					cont.prepend(lbl);
				}
				
				this.set('labelNode', lbl);
			}
			
			if (this.get('disabled')) {
				this.set('disabled', true);
			}
			
			//Move label inside bounding box
			if (lbl && inp && cont.compareTo(inp)) {
				bound.prepend(lbl);
			}
			
			//Add classnames
			bound.addClass(Y.ClassNameManager.getClassName('input'));
			bound.addClass(Y.ClassNameManager.getClassName(this.constructor.NAME));
			
			//Style
			this.set('style', this.get('style'));
			
			//Value
			this.set('value', this.get('value'));
		},
		
		getAttribute: function (key) {
			return this.get('inputNode').getAttribute(key);
		},
		
		addClass: function (c) {
			this.get('boundingBox').addClass(c);
			return this;
		},
		
		removeClass: function (c) {
			this.get('boundingBox').removeClass(c);
			return this;
		},
		
		hasClass: function (c) {
			return this.get('boundingBox').hasClass(c);
		},
		
		toggleClass: function (c, v) {
			this.get('boundingBox').toggleClass(c, v);
			return this;
		},
		
		/**
		 * Show error message
		 * 
		 * @param {String} message
		 */
		showError: function (message) {
			
		},
		
		/**
		 * Hide error message
		 */
		hideError: function () {
			
		},
		
		/**
		 * Error setter
		 * Show error message if 'error' is string or just highlight if true
		 * If false, 0 or empty string, then hide error
		 *
		 * @param {String} error Error message
		 */
		_setError: function (error) {
			this.get('boundingBox').toggleClass('yui3-input-error', error);
			
			if (typeof error == 'string' && error) {
				this.showError(error);
				return error;
			} else {
				this.hideError();
				return false;
			}
		},
		
		/**
		 * Disabled attribute setter
		 * Disable / enable HTMLEditor
		 * 
		 * @param {Boolean} value New state value
		 * @return New state value
		 * @type {Boolean}
		 * @private
		 */
		_setDisabled: function (value) {
			var node = this.get('inputNode');
			if (node) {
				node.set('disabled', !!value);
			}
			
			this.get('boundingBox').toggleClass('yui3-input-disabled', value);
			
			return !!value;
		},
		
		/**
		 * Value attribute getter
		 * 
		 * @param {String} value Previous value
		 * @return New value
		 * @type {String}
		 * @private
		 */
		_getValue: function () {
			return this.get('inputNode').get('value');
		},
		
		/**
		 * saveValue attribute getter
		 * Returns value for sending to server
		 * 
		 * @param {String} value Previous value
		 * @return New value
		 * @type {String}
		 * @private
		 */
		_getSaveValue: function () {
			return this.get('value');
		},
		
		/**
		 * Value attribute setter
		 * 
		 * @param {String} value New value
		 * @return New value
		 * @type {String}
		 * @private
		 */
		_setValue: function (value) {
			value = (value === undefined || value === null ? '' : value);
			this.get('inputNode').set('value', value);
			
			this._original_value = value;
			return value;
		},
		
		/**
		 * Label attribute setter
		 * 
		 * @param {String} lbl Label text
		 * @return New label
		 * @type {String}
		 * @private
		 */
		_setLabel: function (lbl) {
			var node = this.get('labelNode');
			if (node) {
				lbl = Supra.Intl.replace(lbl);
				if (lbl) {
					node.set('text', lbl);
					node.removeClass('hidden');
				} else {
					node.addClass('hidden');
				}
			}
			
			return lbl;
		},
		
		/**
		 * Description attribute setter
		 * 
		 * @param {String} descr Description text
		 * @return New description
		 * @type {String}
		 * @private
		 */
		_setDescription: function (descr) {
			var node = this.get('descriptionNode'),
				inp = this.get('inputNode');
			
			if (!node && !inp) {
				//Can't do anything about it
				return descr;
			}
			if (!node && descr && this.DESCRIPTION_TEMPLATE) {
				node = Y.Node.create(this.DESCRIPTION_TEMPLATE);
				this.get('inputNode').insert(node, 'after');
				this.set('descriptionNode', node);
			}
			if (node) {
				var descr_text = Supra.Intl.replace(descr) || '';
				
				node.set('text', descr_text)
				    .toggleClass('hidden', !descr_text);
			}
			
			return descr;
		},
		
		/**
		 * Set input style
		 * 
		 * @param {String} style Style
		 * @private
		 */
		_setStyle: function (style) {
			var prev = this.get('style'),
				node = this.get('boundingBox');
			
			if (prev) {
				this.removeClass(Y.ClassNameManager.getClassName('input', prev));
			}
			if (style) {
				this.addClass(Y.ClassNameManager.getClassName('input', style));
			}
			
			return style;
		},
		
		/**
		 * Set input value
		 * 
		 * @param {Object} value
		 */
		setValue: function (value) {
			this.set('value', value);
			return this;
		},
		
		/**
		 * Returns input value
		 * 
		 * @return Input value
		 * @type {Object}
		 */
		getValue: function () {
			return this.get('value');
		},
		
		/**
		 * Reset value to default
		 */
		resetValue: function () {
			this.set('value', this.get('defaultValue') || '');
			return this;
		},
		
		/**
		 * Set label
		 * 
		 * @param {String} label
		 */
		setLabel: function (label) {
			this.set('label', label);
			return this;
		},
		
		/**
		 * Returns label
		 * 
		 * @return Label
		 * @type {String}
		 */
		getLabel: function () {
			return this.get('label');
		},
		
		/**
		 * Disable/enable input
		 * 
		 * @param {Boolean} disabled
		 */
		setDisabled: function (disabled) {
			this.set('disabled', disabled);
			return this;
		},
		
		/**
		 * Returns true if input is disabled, otherwise false
		 * 
		 * @return True if input is disabled
		 * @type {Boolean}
		 */
		getDisabled: function () {
			return this.get('disabled');
		},
		
		/**
		 * Add validation rule
		 * 
		 * @param {Object} rule
		 */
		addValidationRule: function (rule) {
			//@TODO
			return this;
		},
		
		/**
		 * Add validation rules
		 * 
		 * @param {Array} rules
		 */
		addValidationRules: function (rules) {
			//@TODO
			return this;
		},
		
		/**
		 * Returns input validation rules
		 * 
		 * @return Array with validation rules
		 * @type {Array}
		 */
		getValidationRules: function () {
			//@TODO
		},
		
		/**
		 * Validate input value against validation rules
		 * 
		 * @return True on success, false on failure
		 * @type {Boolean}
		 */
		validate: function () {
			//@TODO
		},
		
		/**
		 * Returns value as string
		 * 
		 * @return Value
		 * @type {String}
		 */
		toString: function () {
			return String(this.getValue() || '');
		}
		
	});
	
	Supra.Input = {
		'Proto': Input
	};
	
	//Since this widget has Supra namespace, it doesn't need to be bound to each YUI instance
	//Make sure this constructor function is called only once
	delete(this.fn); this.fn = function () {};
	
}, YUI.version, {requires:['widget']});