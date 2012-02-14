//Invoke strict mode
"use strict";

/**
 * Main manager action, initiates all other actions
 */
Supra('supra.input', function (Y) {

	//Shortcut
	var Manager = Supra.Manager;
	var Action = Manager.Action;
	
	
	//Create Action class
	new Action(Action.PluginForm, Action.PluginFooter, {
		
		/**
		 * Unique action name
		 * @type {String}
		 */
		NAME: 'UserDetails',
		
		/**
		 * Load action stylesheet
		 * @type {Boolean}
		 * @private
		 */
		HAS_STYLESHEET: false,
		
		/**
		 * Load action template
		 * @type {Boolean}
		 * @private
		 */
		HAS_TEMPLATE: true,
		
		
		
		/**
		 * Bind Actions together
		 * 
		 * @private
		 */
		render: function () {
			
			var user = Manager.getAction('User');
			
			//On 'Reset password' click show confirmation
			this.footer.getButton('reset').on('click', function () {
				Manager.executeAction('Confirmation', {
					'message': Supra.Intl.get(['userdetails', 'reset_message']),
					'useMask': true,
					'buttons': [
						{'id': 'yes', 'style': 'mid-blue', 'click': this.resetPassword, 'context': this},
						{'id': 'no'}
					]
				});
			}, user);
			
			//On 'Delete user' click show confirmation
			this.footer.getButton('delete').on('click', function () {
				Manager.executeAction('Confirmation', {
					'message': Supra.Intl.get(['userdetails', 'delete_message']),
					'useMask': true,
					'buttons': [
						{'id': 'yes', 'style': 'mid-red', 'click': this.deleteUser, 'context': this},
						{'id': 'no'}
					]
				});
			}, user);
			
			//On form values change update data
			this.form.on('change', this.onDataChange, this);
			
			//On avatar click open avatar list
			this.one('div.info em').on('click', function (event) {
				Manager.executeAction('UserAvatar');
				event.halt();
			});
		},
		
		/**
		 * Update UI
		 * 
		 * @param {Object} data User data
		 * @private
		 */
		updateUI: function (data) {
			
			if ('avatar' in data) {
				this.one('div.info img').setAttribute('src', data.avatar + '?r=' + (+new Date()));
			}
			if ('name' in data) {
				this.one('div.info a').set('text', data.name || Supra.Intl.get(['userdetails', 'default_name']));
			}
			if ('group' in data) {
				this.one('div.info b').set('text', Supra.Intl.get(['userdetails', 'group_' + data.group]));
			}
			
		},
		
		/**
		 * On change update user data with form values
		 */
		onDataChange: function () {
			var values = this.form.getValues('name'),
				data = Manager.getAction('User').getData();

			//Mix form values into data
			var avatar = data.avatar,
				avatar_id = data.avatar_id;
			Supra.mix(data, values);
			data.avatar = avatar;
			data.avatar_id = avatar_id;
			
			this.updateUI(data);
		},
		
		/**
		 * Set user data
		 * 
		 * @param {Object} data User data
		 * @private
		 */
		setUserData: function (data /* User data */) {

			this.updateUI(data);
			
			this.form.setValues(data, 'name');
			
			if (data.user_id) {
				//If there is a user, then show 'Reset password' and 'Delete' buttons
				this.footer.show();
			} else {
				//If there is no user, then hide buttons
				this.footer.hide();
			}
			
		},
		
		/**
		 * Try creating new user
		 */
		createNewUser: function () {
			/* @TODO Add correct validation */
			
			var data = Manager.getAction('User').getData(),
				error = false,
				input_name = this.form.getInput('name'),
				input_email = this.form.getInput('email');
			
			if (!data.name) {
				input_name.set('error', true);
				error = true;
			} else {
				input_name.set('error', false);
			}
			
			if (!data.email) {
				input_email.set('error', true);
				error = true;
			} else {
				input_email.set('error', false);
			}
			
			if (!error) {
				Manager.User.save(Y.bind(function (data, status) {
					if (status) {
						//Mix data into cache
						var user_data = Manager.getAction('User').getData();
						Supra.mix(user_data, data);
						
						//Update UI
						this.updateUI(data);
						
						//Open permission tab
						Manager.executeAction('UserPermissions');
						Manager.PageToolbar.buttons.details.set('down', false);
					}
				}, this));
			}
		},
		
		/**
		 * Execute action
		 */
		execute: function () {
			//Slide
			this.show();
			
			var user = Manager.getAction('User');
			user.slideshow.set('slide', this.NAME);
			
			//Update UI with user data
			this.setUserData(user.getData());
		}
	});
	
});