
/* global Ext, go */

go.users.UserDefaultsWindow = Ext.extend(go.Window, {
	title: t("User settings"),
	autoScroll: true,
	modal: true,
	width: dp(800),
	height: dp(600),
	layout: 'fit',
	initComponent: function () {


		this.formPanel = new Ext.form.FormPanel({
			title: t("Defaults"),
			items: [
				{
					layout: "hbox",
					items: [
						{
							flex: 1,
							xtype: "fieldset",
							title: t("Regional"),
							labelWidth: dp(160),
							items: [
								new Ext.form.ComboBox({
									fieldLabel: t("Timezone"),
									name: 'defaultTimezone',
									store: new Ext.data.SimpleStore({
										fields: ['timezone'],
										data: go.TimeZones
									}),
									displayField: 'timezone',
									mode: 'local',
									triggerAction: 'all',
									selectOnFocus: true,
									forceSelection: true
								}), new Ext.form.ComboBox({
									fieldLabel: t('Date format', "users", "core"),

									store: go.util.Format.dateFormats,
									displayField: 'label',
									valueField: 'format',
									hiddenName: 'defaultDateFormat',
									mode: 'local',
									triggerAction: 'all',
									editable: false,
									selectOnFocus: true,
									forceSelection: true
								}),
								new Ext.form.ComboBox({
									fieldLabel: t("Time Format", "users", "core"),
									store: go.util.Format.timeFormats,
									displayField: 'label',
									valueField: 'format',
									hiddenName: 'defaultTimeFormat',
									mode: 'local',
									triggerAction: 'all',
									editable: false,
									selectOnFocus: true,
									forceSelection: true
								}),
								{
									xtype: "xcheckbox",
									name: "defaultShortDateInList",
									checked: true,
									hideLabel: true,
									boxLabel: t("Use short format for date and time in lists", 'users', 'core')
								},
								new Ext.form.ComboBox({
									fieldLabel: t("First weekday", "users", "core"),
									store: new Ext.data.SimpleStore({
										fields: ['id', 'day'],
										data: [
											[0, t("Sunday")],
											[1, t("Monday")]
										]
									}),
									displayField: 'day',
									valueField: 'id',
									hiddenName: 'defaultFirstWeekday',
									mode: 'local',
									triggerAction: 'all',
									editable: false,
									selectOnFocus: true,
									forceSelection: true,
									value: 1
								})
							]
						},

						{
							flex: 1,
							xtype: "fieldset",
							labelWidth: dp(240),
							defaults: {width: dp(50)},
							title: t('Formatting'),
							items: [
								{
									xtype: 'textfield',
									fieldLabel: t("List separator", "users", "core"),
									name: 'defaultListSeparator'
								}, {
									xtype: 'textfield',
									fieldLabel: t("Text separator", "users", "core"),
									name: 'defaultTextSeparator'
								}, {
									xtype: 'textfield',
									fieldLabel: t("Thousand Seperator", "users", "core"),
									name: 'defaultThousandSeparator'
								},
								{
									xtype: 'textfield',
									fieldLabel: t("Decimal Seperator", "users", "core"),
									name: 'defaultDecimalSeparator'
								}, {
									xtype: "textfield",
									name: "defaultCurrency",
									value: "€",
									fieldLabel: t("Currency", "users", "core")
								}

							]
						}


					]
				},
				{
					title: t("Other"),
					xtype: "fieldset",
					items: [
						new go.form.multiselect.Field({
							valueIsId: true,
							hint: t("Users will automatically be added to these groups", "users", "core"),
							name: "defaultGroups",
							idField: "groupId",
							displayField: "name",
							entityStore: "Group",

							fieldLabel: t("Groups"),
							storeBaseParams: {
								filter: {hideUsers: true, excludeEveryone: true}
							}
						})]
				}
			]
		});
		
		this.tabPanel = new Ext.TabPanel({
			activeTab: 0,
			items: [this.formPanel, new go.customfields.SystemSettingsPanel({
					title: t("Custom fields"),
					toolbarTitle: false,
					entity: "User"
				})]
		});


		this.items = [this.tabPanel];

		this.bbar = ['->', {
				text: t("Save"),
				handler: function () {
					this.submit();
				},
				scope: this
			}];

		go.users.UserDefaultsWindow.superclass.initComponent.call(this);


		this.on('render', function () {	
			this.formPanel.getForm().setValues(go.Modules.get('core', 'core').settings);
		}, this);
	},

	submit: function () {

		this.getEl().mask();
		
		var module = go.Modules.get('core', 'core'), p = {"update": {}};
		
		p.update[module.id] = {settings: this.formPanel.getForm().getFieldValues()};
		
		go.Stores.get("Module").set(p, function (options, success, response) {
			this.getEl().unmask();
			this.close();
		},
		this);
	}

});



