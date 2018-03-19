go.modules.community.imapauthenticator.ServerForm = Ext.extend(go.form.FormWindow, {
	title: t('Server profile', 'imapauth'),
	entityStore: go.stores.ImapAuthServer,	
	width: dp(400),
	height: dp(600),
	autoScroll: true,
	initFormItems: function () {
		return [{
				title: 'IMAP Server',
				xtype: 'fieldset',
				defaults: {
					anchor: '100%'
				},
				items: [
					{
						xtype: 'textfield',
						name: 'imapHostname',
						fieldLabel: t("Hostname", "imapauthenticator"),
						required: true
					}, {
						xtype: 'numberfield',
						decimals: 0,
						name: 'imapPort',
						fieldLabel: t("Port", "imapauthenticator"),						
						required: true,
						value: 143
					}, {
						xtype: 'combo',
						name: 'imapEncryption',
						fieldLabel: t('Encryption'),
						mode: 'local',
						editable: false,
						triggerAction: 'all',
						store: new Ext.data.ArrayStore({
							fields: [
								'value',
								'display'
							],
							data: [['tls', 'TLS'], ['ssl', 'SSL'], [null, 'None']]
						}),
						valueField: 'value',
						displayField: 'display',
						value: 'tls'
					}, {
						xtype: 'xcheckbox',
						checked: true,
						hideLabel: true,
						boxLabel: t('Validate certificate'),
						name: 'imapValidateCertificate'
					},{
						xtype: 'xcheckbox',
						hideLabel: true,
						boxLabel: t('Remove domain from username', 'imapauthenticator'),
						name: 'removeDomainFromUsername',
						hint: t("Users must login with their full e-mail adress. Enable this option if the IMAP excepts the username without domain.")
					}]
			}, {
				title: 'SMTP Server',
				xtype: 'fieldset',
				defaults: {
					anchor: '100%'
				},
				items: [{
						xtype: 'textfield',
						name: 'smtpHostname',
						fieldLabel: t('Hostname'),
					}, {
						xtype: 'numberfield',
						name: 'smtpPort',
						fieldLabel: t('Port'),
						decimals: 0,
						value: 587
					},  {
						xtype: 'xcheckbox',
						hideLabel: true,
						boxLabel: t('Use user credentials', 'imapauthenticator'),
						name: 'smtpUseUserCredentials',
						hint: t("Enable this if the SMTP server credentials are identical to the IMAP server.", "imapauthenticator"),
						listeners: {
							check: function(checkbox, checked) {
								this.formPanel.getForm().findField('smtpUsername').setDisabled(checked);
								this.formPanel.getForm().findField('smtpPassword').setDisabled(checked);
							},
							scope: this
						}
					},{
						xtype: 'textfield',
						name: 'smtpUsername',
						fieldLabel: t('Username')
					}, {
						xtype: 'textfield',
						name: 'smtpPassword',
						fieldLabel: t('Password')
					}, {
						xtype: 'combo',
						name: 'smtpEncryption',
						fieldLabel: t('Encryption'),
						mode: 'local',
						editable: false,
						triggerAction: 'all',
						store: new Ext.data.ArrayStore({
							fields: [
								'value',
								'display'
							],
							data: [['tls', 'TLS'], ['ssl', 'SSL'], [null, 'None']]
						}),
						valueField: 'value',
						displayField: 'display',
						value: 'tls'
					}, {
						xtype: 'xcheckbox',
						hideLabel: true,
						boxLabel: t('Validate certificate'),
						name: 'smtpValidateCertificate',
						checked: true
					}]
			}
		];
	}
});

