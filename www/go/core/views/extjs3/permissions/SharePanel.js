go.permissions.SharePanel = Ext.extend(go.grid.EditorGridPanel, {
	/*
	 * the form field name
	 */
	name: "acl",
	
	cls: "go-share-panel", 
	
	clicksToEdit: 1,
	
	showLevels: true,

	title: t("Permissions"),
	
	initComponent: function () {
		
		if(!this.value) {
			this.value = [];
		}
		
		var checkColumn = new GO.grid.CheckColumn({
			width: dp(64),
			dataIndex: 'selected',
			hideable: false,
			sortable: false,
			menuDisabled: true,
			listeners: {
				change: this.onCheckChange,
				scope: this
			},
			isDisabled : function(record) {
				return record.data.id === 1;
			}
		});
		
		var me = this;
		
		this.store = new go.data.Store({
			sortInfo: {
				field: 'name',
				direction: 'ASC'
			},
			fields: [
				'id', 
				'name', 
				{name: 'user', type: "relation"}, //fetches entity from store
				{name: 'users', type: "relation", limit: 5},
				{
					name: 'level', 
					type: {
						convert: function (v, data) {							
							return me.value[data.id];
						}
					}
				},
				{
					name: 'selected', 
					type: {
						convert: function (v, data) {
							return me.getSelectedGroupIds().indexOf(data.id) > -1;
						}
					},
					sortType:function(checked) {
						return checked ? 1 : 0;
					}
				}
			],

			baseParams: {
			},
			entityStore: "Group"
		});
		
		var levelCombo = this.createLevelCombo();

		Ext.apply(this, {		
			plugins: [checkColumn],
			tbar: [
			'->', 
				{
					xtype: 'tbsearch',
					filters: [
						'text'					
					]
				}
			],
			columns: [
				{
					id: 'name',
					header: t('Name'),
					sortable: false,
					dataIndex: 'name',
					menuDisabled: true,
					hideable: false,
					renderer: function (value, metaData, record, rowIndex, colIndex, store) {
						
						var user = record.get("user"),
										style = user && user.avatarId ?  'background-image: url(' + go.Jmap.downloadUrl(record.get("user").avatarId) + ')"' : "background: linear-gradient(rgba(0, 0, 0, 0.38), rgba(0, 0, 0, 0.24));";
										html = user ? "" : '<i class="icon">group</i>';

							memberStr = record.get('users').column('displayName').join(", ");								
							var more = record.json._meta.users.total - store.fields.item('users').limit;
							if(more > 0) {
								memberStr += t(" and {count} more").replace('{count}', more);
							}					
						
						return '<div class="user"><div class="avatar" style="' + style + '">' + html + '</div>' +
							'<div class="wrap">'+
								'<div class="displayName">' + value + '</div>' +
								'<small class="username">' + memberStr + '</small>' +
							'</div>'+
							'</div>';
					}
				},{
					id: 'level',
					header : t("Level"),
					dataIndex : 'level',
					menuDisabled:true,
					editor : levelCombo,
					width: dp(260),
					hidden: !this.showLevels,
					hideable: false,
					renderer:function(v, meta){
						if(!me.showLevels) {
							return "";
						}
						var r = levelCombo.store.getById(v);
						meta.style="position:relative";
						return r ? r.get('text') + "<i class='trigger'>arrow_drop_down</i></div>" : v;
					},
					sortable: true
				},
				checkColumn
			],
			viewConfig: {
				emptyText: 	'<i>description</i><p>' +t("No items to display") + '</p>',		
				scrollOffset: 0		
			},
			autoExpandColumn: 'name',
			listeners: {
				scope: this,
				afteredit : this.afterEdit
			}
//			// config options for stateful behavior
//			stateful: true,
//			stateId: 'users-grid'
		});
		
		this.store.on("beforeload", this.onBeforeStoreLoad, this);
		
		go.permissions.SharePanel.superclass.initComponent.call(this);
		
		this.on("beforeedit", function(e) {
			return e.record.data.id !== 1; //cancel edit for admins group
		}, this);

	},
	
	startEditing : function(row,  col) {
		go.permissions.SharePanel.superclass.startEditing.call(this, row, col);
		
		//expand combo when editing
		if(this.activeEditor) {
			this.activeEditor.field.onTriggerClick();
		}
	},
	
	onCheckChange : function(record, newValue) {
		if(newValue) {			
			record.set('level', this.addLevel);
			this.value[record.data.id] = record.data.level;
		} else
		{
			record.set('level', null);
			this.value[record.data.id] = null;
		}
		
		this._isDirty = true;
	},

	afterEdit : function(e) {
		this.value[e.record.id] = e.record.data.level;				
		this._isDirty = true;
	},
	
	createLevelCombo : function() {
		var levelData = [];

		this.levelLabels = this.levelLabels || {};
		
		if(!this.levelLabels[go.permissionLevels.read])
			this.levelLabels[go.permissionLevels.read] =t("Read only");
		if(!this.levelLabels[go.permissionLevels.create])
			this.levelLabels[go.permissionLevels.create] =t("Read and Create only");
		if(!this.levelLabels[go.permissionLevels.write])
			this.levelLabels[go.permissionLevels.write] =t("Write");
		if(!this.levelLabels[go.permissionLevels.writeAndDelete])
			this.levelLabels[go.permissionLevels.writeAndDelete] =t("Write and delete");
		if(!this.levelLabels[go.permissionLevels.manage])
			this.levelLabels[go.permissionLevels.manage] =t("Manage");
		
		if(!this.levels){
			this.levels=[
				go.permissionLevels.read,
				go.permissionLevels.create,
				go.permissionLevels.write,
				go.permissionLevels.writeAndDelete,
				go.permissionLevels.manage
			];
		}
		
		for(var i=0;i<this.levels.length;i++){			
			if(!this.levelLabels[this.levels[i]]){
				alert('Warning: you must define a label for permission level: '+this.levels[i]);
			}else
			{
				levelData.push([this.levels[i],this.levelLabels[this.levels[i]]]);
			}
		}
		

		this.showLevel = (this.hideLevel) ? false : true;			

		var permissionLevelConfig ={
					store : new Ext.data.SimpleStore({
						id:0,
						fields : ['value', 'text'],
						data : levelData
					}),
					valueField : 'value',
					displayField : 'text',
					mode : 'local',
					triggerAction : 'all',
					editable : false,
					selectOnFocus : true,
					forceSelection : true
				};
				
		
		if(!this.addLevel)
			this.addLevel = go.permissionLevels.read;
		
		return new go.form.ComboBox(permissionLevelConfig);
	},
	
	afterRender : function() {

		go.permissions.SharePanel.superclass.afterRender.call(this);

		var form = this.findParentByType("entityform");

		if(!form) {
			return;
		}
		this.value = form.entityStore.entity.defaultAcl;

		form.on("load", function(f, v) {
			this.setDisabled(v.permissionLevel < go.permissionLevels.manage);
		}, this);

		//Check form currentId becuase when form is loading then it will load the store on setValue later.
		//Set timeout is used to make sure the check will follow after a load call.
		var me = this;
		setTimeout(function() {
			if(!go.util.empty(me.value) && !form.currentId) {				
				me.store.load().catch(function(){}); //ignore failed load becuase onBeforeStoreLoad can return false
			}
		}, 0);		
	},
	
	isFormField: true,

	getName: function() {
		return this.name;
	},

	_isDirty: false,

	isDirty: function () {
		return this._isDirty || this.store.getModifiedRecords().length > 0;
	},

	reset : function() {
		this.setValue([]);
		this.dirty = false;
	},

	setValue: function (groups) {		
		this._isDirty = false;		
		this.value = groups;
		this.store.load().catch(function(){}); //ignore failed load becuase onBeforeStoreLoad can return false
	},
	
	getSelectedGroupIds : function() {
		return Object.keys(this.value).map(function(id) { return parseInt(id);});
	},
	
	onBeforeStoreLoad : function(store, options) {

		//don't add selected on search
		if(this.store.filters.tbsearch || options.selectedLoaded || options.paging) {
			this.store.setFilter('exclude', null);
			return true;
		}
		
		go.Db.store("Group").get(this.getSelectedGroupIds(), function(entities) {
			this.store.loadData({records: entities}, true);
			this.store.sortData();
			this.store.setFilter('exclude', {
				exclude: this.getSelectedGroupIds()
			});
			
			this.store.load({
				add: true,
				selectedLoaded: true
			});
		}, this);
		
		return false;
	},	
	
	getValue: function () {				
		return this.value;
	},

	markInvalid: function (msg) {
		this.getEl().addClass('x-form-invalid');
		Ext.form.MessageTargets.qtip.mark(this, msg);
	},
	clearInvalid: function () {
		this.getEl().removeClass('x-form-invalid');
		Ext.form.MessageTargets.qtip.clear(this);
	},
	
	validate : function() {
		return true;
	}
});


