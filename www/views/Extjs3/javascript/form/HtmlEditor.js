



GO.form.HtmlEditor = function (config) {
	config = config || {};

	Ext.applyIf(config, {
		border: false,
		enableFont: false,
		headingsMenu: true
	});

	config.plugins = config.plugins || [];

	if (!Ext.isArray(config.plugins))
		config.plugins = [config.plugins];

	var spellcheckInsertPlugin = new GO.plugins.HtmlEditorSpellCheck(this);
	var wordPastePlugin = new Ext.ux.form.HtmlEditor.Word();
	//var dividePlugin = new Ext.ux.form.HtmlEditor.Divider();
	//var tablePlugin = new Ext.ux.form.HtmlEditor.Table();
	var hrPlugin = new Ext.ux.form.HtmlEditor.HR();
	var ioDentPlugin = new Ext.ux.form.HtmlEditor.IndentOutdent();
	var ssScriptPlugin = new Ext.ux.form.HtmlEditor.SubSuperScript();
	var rmFormatPlugin = new Ext.ux.form.HtmlEditor.RemoveFormat();


	if (GO.settings.pspellSupport)
		config.plugins.unshift(spellcheckInsertPlugin);

	config.plugins.unshift(
					ioDentPlugin,
					rmFormatPlugin,
					wordPastePlugin,
					hrPlugin,
					ssScriptPlugin
					);

	if(config.headingsMenu) {
		var headingMenu = new Ext.ux.form.HtmlEditor.HeadingMenu();
		config.plugins.unshift(headingMenu);
	}

	GO.form.HtmlEditor.superclass.constructor.call(this, config);
};

Ext.extend(GO.form.HtmlEditor, Ext.form.HtmlEditor, {

	iframePad:dp(8),

	toolbarHidden: false,

	headingsMenu: true,

	// hideToolbar : function() {
	// 	this.toolbarHidden = true;
	//
	// 	if(this.initialized) {
	// 		this.wrap.dom.firstChild.style.position = 'absolute';
	// 		this.wrap.dom.firstChild.style.top = "-1000000px";
	// 		this.wrap.dom.firstChild.style.height = "0px";
	// 	}
	// },
	//
	// showToolbar : function*() {
	//
	// 	this.toolbarHidden = false;
	//
	// 	if(this.initialized) {
	// 		this.wrap.dom.firstChild.style.position = 'relative';
	// 		delete this.wrap.dom.firstChild.style.top;
	// 		this.wrap.dom.firstChild.style.height = dp(40) + "px";
	// 	}
	// },

	initComponent: function() {
		GO.form.HtmlEditor.superclass.initComponent.apply(this);

		this.on('afterrender', function() {
			if(this.grow && this.growMinHeight < dp(36)) {
				this.tb.hide();
			}
		}, this);
		this.on('initialize', function(){

			if(this.grow) {



				this.doGrow();
				this.on("sync", this.doGrow, this);
			}

			// if(Ext.isEmpty(this.emptyText)) {
			// 	return;
			// }
			// Ext.EventManager.on(this.getEditorBody(),{
			// 	focus:this.handleEmptyText,
			// 	blur:this.applyEmptyText,
			// 	scope:this
			// });




		},this);

		this.on('activate', function() {
			this.registerSubmitKey();
		}, this);
	},

	emptyTextRegex: '<span[^>]+[^>]*>{0}<\/span>',
	emptyTextTpl: '<span style="color:#ccc;">{0}</span>',
	emptyText: '',

	registerSubmitKey: function() {
		var doc = this.getDoc();
		if (Ext.isGecko){
			Ext.EventManager.on(doc, {
				keydown: this.fireSubmit,
				scope: this
			});
		}

		if (Ext.isIE || Ext.isWebKit || Ext.isOpera) {
			Ext.EventManager.on(doc, 'keydown', this.fireSubmit,
				this);
		}
	},

	fireSubmit : function(e) {
		if (e.ctrlKey && Ext.EventObject.ENTER == e.getKey()) {
			e.preventDefault();
			this.fireEvent('ctrlenter',this);
			return false;
		}
	},



	// applyEmptyText: function() {
	// 	var value = this.getValue();
	// 	if(Ext.isEmpty(value)) {
	// 		var emptyText = go.util.Format.string(this.emptyTextTpl,this.emptyText);
	// 		go.form.HtmlEditor.superclass.setValue.apply(this, [emptyText]);
	// 	}
	// },
	// handleEmptyText: function() {
	// 	var value = this.getValue(),
	// 		regex = new RegExp(go.util.Format.string( this.emptyTextRegex,this.emptyText ) );
	// 	if(!Ext.isEmpty(value) && regex.test(value)) {
	// 		go.form.HtmlEditor.superclass.setValue.apply(this, ['']);
	// 	}
	// },
	// setValue : function(v){
	// 	go.form.HtmlEditor.superclass.setValue.apply(this, arguments);
	// 	//this.applyEmptyText();
	// 	return this;
  //  },

	initEditor: function () {

		GO.form.HtmlEditor.superclass.initEditor.call(this);

		this.addEvents({attach: true});

		//Following is needed when using animateTarget in windows. But since that doesn't perform well we should look at using css transitions instead of js animations
		//this.getToolbar().doLayout();
		var doc = this.getEditorBody();

		doc.addEventListener('paste', this.onPaste.createDelegate(this));
		doc.addEventListener('drop', this.onDrop.createDelegate(this));
		doc.addEventListener('keyup', this.onKeyUp.createDelegate(this));
		// this.on('beforesync', this.onInput, this);

		//Fix for Tooltips in the way of email #276
		doc.addEventListener("mouseenter", function() {
			setTimeout(function() {
				Ext.QuickTips.getQuickTip().hide();
			}, 500);

		});

		go.ActivityWatcher.registerDocument(doc);

		//other browsers are already registered in parent function
		if(Ext.isGecko) {
			Ext.EventManager.on(doc, 'keydown', this.fixKeys, this);
		}

		if(Ext.isChrome && navigator.appVersion.indexOf("Chrome/96.") > -1) {
			console.warn("Disable spell check because it's slow on Chrome v96.")

			doc.spellcheck = false;
		}

	},

	debounceTimeout : null,

	onKeyUp : function(e) {

		//Only run on enter, space or tab
		if(e.keyCode != 13 && e.keyCode != 32 && e.keyCode != 9) {
			return;
		}

		clearTimeout(this.debounceTimeout);
		this.debounceTimeout = setTimeout( () => {
			clearTimeout(this.debounceTimeout);
			this.storeCursorPosition();
			var h = this.getEditorBody().innerHTML;
			var anchored = Autolinker.link(h, {
				stripPrefix: false,
				stripTrailingSlash: false,
				className: "normal-link",
				newWindow: true,
				phone: false,
				urls: {
					schemeMatches : true,
					wwwMatches    : false,
					tldMatches    : false
				}
			});

			if(h != anchored) {
				this.getEditorBody().innerHTML = anchored;
				this.restoreCursorPosition();
			}else
			{
				this.forgetCursorPosition();
			}

		}, 500);
	},

	storeCursorPosition : function() {
		this.insertAtCursor("<div style='display:none' id='go-stored-cursor'></div>");
	},

	forgetCursorPosition: function() {
		var cursor = this.getDoc().getElementById("go-stored-cursor");
		if(cursor) {
			cursor.remove();
		}
	},

	restoreCursorPosition : function() {
		var doc = this.getDoc(), sel = this.getWin().getSelection();
		var cursor = doc.getElementById("go-stored-cursor");
		if(!cursor) {
			return false;
		}
		var range = new Range();
		range.setStart(cursor, 0);
		range.collapse(true);
		sel.removeAllRanges();
		sel.addRange(range);
		cursor.remove();
	},

	onDrop: function(e) {
		if(!e.dataTransfer.files) {
			return;
		}

		//prevent browser from navigating to dropped file
		e.preventDefault();

		//make sure editor has focus
		this.focus();

		//this is needed if the editor has not been activated yet.
		this.updateToolbar();

		Array.from(e.dataTransfer.files).forEach(function(file) {
			go.Jmap.upload(file, {
				scope: this,
				success: function(response) {
					console.warn(response);
					var imgEl = null;
					if (file.type.match(/^image\//)) {
						var domId = Ext.id(), img = '<img style="max-width: 100%" id="' + domId + '" src="' + go.Jmap.downloadUrl(response.blobId) + '" alt="' + file.name + '" />';
						this.insertAtCursor(img);
						imgEl = this.getDoc().getElementById(domId);
					}

					this.fireEvent('attach', this, response, file, imgEl);
				}
			});
		}, this);

	},

// 	getCursorPosition : function() {
// 		var win = this.getWin();
//
// 		// node_walk: walk the element tree, stop when func(node) returns false
// 		function node_walk(node, func) {
// 			var result = func(node);
// 			for(node = node.firstChild; result !== false && node; node = node.nextSibling)
// 				result = node_walk(node, func);
// 			return result;
// 		};
//
//
// // getCaretPosition: return [start, end] as offsets to elem.textContent that
// //   correspond to the selected portion of text
// //   (if start == end, caret is at given position and no text is selected)
// 		function getCaretPosition(elem) {
// 			var sel = win.getSelection();
// 			var cum_length = [0, 0];
//
// 			if(sel.anchorNode == elem)
// 				cum_length = [sel.anchorOffset, sel.extentOffset];
// 			else {
// 				var nodes_to_find = [sel.anchorNode, sel.extentNode];
// 				if(!elem.contains(sel.anchorNode) || !elem.contains(sel.extentNode))
// 					return undefined;
// 				else {
// 					var found = [0,0];
// 					var i;
// 					node_walk(elem, function(node) {
// 						for(i = 0; i < 2; i++) {
// 							if(node == nodes_to_find[i]) {
// 								found[i] = true;
// 								if(found[i == 0 ? 1 : 0])
// 									return false; // all done
// 							}
// 						}
//
// 						if(node.textContent && !node.firstChild) {
// 							for(i = 0; i < 2; i++) {
// 								if(!found[i])
// 									cum_length[i] += node.textContent.length;
// 							}
// 						}
// 					});
// 					cum_length[0] += sel.anchorOffset;
// 					cum_length[1] += sel.extentOffset;
// 				}
// 			}
// 			if(cum_length[0] <= cum_length[1])
// 				return cum_length;
// 			return [cum_length[1], cum_length[0]];
// 		}
//
// 		return getCaretPosition(this.getDoc().body)[1];
// 	},
//
// 	setCursorPosition :  function(pos) {
//
// 		var win = this.getWin();
// 		var doc = this.getDoc();
//
// 		function createRange(node, chars, range) {
// 			if (!range) {
// 				range = doc.createRange()
// 				range.selectNode(node);
// 				range.setStart(node, 0);
// 			}
//
// 			if (chars.count === 0) {
// 				range.setEnd(node, chars.count);
// 			} else if (node && chars.count >0) {
// 				console.warn(node);
// 				if (node.nodeType === Node.TEXT_NODE) {
// 					if (node.textContent.length < chars.count) {
// 						chars.count -= node.textContent.length;
// 					} else {
// 						range.setEnd(node, chars.count);
// 						chars.count = 0;
// 					}
// 				} else {
// 					for (var lp = 0; lp < node.childNodes.length; lp++) {
// 						range = createRange(node.childNodes[lp], chars, range);
//
// 						if (chars.count === 0) {
// 							break;
// 						}
// 					}
// 				}
// 			}
//
// 			return range;
// 		}
//
//
// 		var sel = win.getSelection();
// 		var range = createRange(doc.body, {count: pos});
//
// 		range.collapse(false);
// 		sel.removeAllRanges();
// 		sel.addRange(range);
// 	},

	onPaste: function (e) {
		var clipboardData = e.clipboardData, me = this;
		if (clipboardData.items) {
			//Chrome /safari has clibBoardData.items
			for (var i = 0, l = clipboardData.items.length; i < l; i++) {
				var item = clipboardData.items[i];

				// item.getAsString((s) => {
				// 	console.warn(item.type);
				// 	console.warn(s);
				// })

				//Some times clipboard data holds multiple versions. When copy pasting from excel you get html, plain text and an image.
				//We prefer to use the html in that case so we exit if found.
				if (item.type == 'text/html') {
					e.preventDefault();
					item.getAsString((s) => {
						//convert style in the head to an inline style tag
						const inlined = go.util.convertStyleToInline(s);
						this.execCmd("insertHTML", inlined);
						// this.insertAtCursor(inlined);
					});
					return;
				}

				if (item.type.match(/^image\//)) {
					
					e.preventDefault();
					var reader = new FileReader();
					reader.onload = function (event) {
						me.handleImage(event.target.result);
					}
					reader.readAsDataURL(item.getAsFile());
				}
			}
		} else
		{
			//Firefox
			if (-1 === Array.prototype.indexOf.call(clipboardData.types, 'text/plain')) {					
				this.findImageInEditor();
			}
		}


	},
	
	findImageInEditor: function () {
		var el = this.getDoc();

		var images = el.getElementsByTagName('img');
		var timespan = Math.floor(1000 * Math.random());
		for (var i = 0, len = images.length; i < len; i++) {
			images[i]["_paste_marked_" + timespan] = true;
		}
		setTimeout(function () {
			var newImages = el.getElementsByTagName('img');
			for (var i = 0, len = newImages.length; i < len; i++) {
				if (!newImages[i]["_paste_marked_" + timespan]) {
					this._handleImage(newImages[i].src);
					newImages[i].remove();
				}
			}

		}, 1);
	},
	
	insertImage : function(src) {
		var domId = Ext.id(), img = '<img style="max-width: 100%" id="' + domId + '" src="' + src + '" alt="pasted image" />';
		this.insertAtCursor(img);
		
		return  this.getDoc().getElementById(domId);		
	},

	handleImage: function (src) {



		var dataURLtoBlob = function (dataURL, sliceSize) {
			var b64Data, byteArray, byteArrays, byteCharacters, byteNumbers, contentType, i, m, offset, slice, _ref;
			if (sliceSize == null) {
				sliceSize = 512;
			}
			if (!(m = dataURL.match(/^data\:([^\;]+)\;base64\,(.+)$/))) {
				return null;
			}
			_ref = m, m = _ref[0], contentType = _ref[1], b64Data = _ref[2];
			byteCharacters = atob(b64Data);
			byteArrays = [];
			offset = 0;
			while (offset < byteCharacters.length) {
				slice = byteCharacters.slice(offset, offset + sliceSize);
				byteNumbers = new Array(slice.length);
				i = 0;
				while (i < slice.length) {
					byteNumbers[i] = slice.charCodeAt(i);
					i++;
				}
				byteArray = new Uint8Array(byteNumbers);
				byteArrays.push(byteArray);
				offset += sliceSize;
			}
			return new Blob(byteArrays, {
				type: contentType
			});
		};

		var loader, me = this;
		loader = new Image();
		loader.onload = function () {
			var blob, canvas, ctx, dataURL;
			canvas = document.createElement('canvas');
			canvas.width = loader.width;
			canvas.height = loader.height;
			ctx = canvas.getContext('2d');
			ctx.drawImage(loader, 0, 0, canvas.width, canvas.height);
			dataURL = null;
			try {
				dataURL = canvas.toDataURL('image/png');
				blob = dataURLtoBlob(dataURL);
			} catch (_error) {
			}

			if (dataURL) {
				var file = new File([blob], "pasted-image." + blob.type.substring(6),{type: blob.type});

				var imgEl = me.insertImage(BaseHref + "views/Extjs3/themes/" + go.User.theme + "/img/loading-spinner.gif");
				imgEl.setAttribute("style", "width: 80px; height: 80px");

				go.Jmap.upload(file, {
					success: function(response) {
						imgEl.setAttribute("src", go.Jmap.downloadUrl(response.blobId));
						imgEl.setAttribute('style', 'max-width: 100%');
						me.fireEvent('attach', me, response.blobId, file, imgEl);
					}
				});

			}
		};

		return loader.src = src;
	},

	/**
	 * Executes a Midas editor command directly on the editor document.
	 * For visual commands, you should use {@link #relayCmd} instead.
	 * <b>This should only be called after the editor is initialized.</b>
	 * @param {String} cmd The Midas command
	 * @param {String/Boolean} value (optional) The value to pass to the command (defaults to null)
	 */
	execCmd: function (cmd, value) {
		var doc = this.getDoc();

		if (cmd === 'createlink' && Ext.isGecko) {
			// If firefox is used, then manually add the "a" tag to the text
			var t = this.getSelectedText();
			if (t.length < 1) {
				value = '<a href="' + value + '">' + value + "</a>";
				this.insertAtCursor(value);
			}
		}

		doc.execCommand(cmd, false, value === undefined ? null : value);

		this.syncValue();
	},

	getSelectedText: function () {

		var frame = this.iframe;
		var frameWindow = frame.contentWindow;
		var frameDocument = frameWindow.document;

		if (frameDocument.getSelection)
			return frameDocument.getSelection().toString();
		else if (frameDocument.selection)
			return frameDocument.selection.createRange().text;
	},

	setValue: function (value) {

		if (this.win && Ext.isChrome && this.activated) {

			//set cursor position on top
			var range = this.win.document.createRange();
			range.setStart(this.win.document.body, 0);
			range.setEnd(this.win.document.body, 0);

			var sel = this.win.document.getSelection();

			if(sel) {
				sel.removeAllRanges();
				sel.addRange(range);
			}
		}
		GO.form.HtmlEditor.superclass.setValue.call(this, value);
	},

	/**
	 * Automatically grow field with content
	 */
	grow: false,

	/**
	 * Minimum height for field
	 */
	growMinHeight: dp(30),

	/**
	 * Maximum height for field
	 */
	growMaxHeight: dp(480),

	// syncValue: function(){
	// 	//In BasicForm.js this method is called by EXT
	// 	// When using the editor in sourceEdit then it may not call the syncValue function
	// 	if(!this.sourceEditMode){
	// 		GO.form.HtmlEditor.superclass.syncValue.call(this);
	//
	// 	}
	// },

	doGrow : function() {
		var body = this.getEditorBody();

		body.style.height = 'auto';
		body.style.display = 'inline-block';

		body.style.minHeight =  this.growMinHeight + "px";
		body.style.padding = dp(6) + "px " + dp(8) + "px";
		body.style.boxSizing = "border-box";
		body.style.width = "100%";
		body.style.lineHeight = dp(20) + "px";

		var h =  Math.max(this.growMinHeight, body.offsetHeight); // 400  max height

		if(h > 36) {
			this.tb.show();
			//workaround for combo
			if(this.tb.items.itemAt(0).wrap) {
				this.tb.items.itemAt(0).wrap.dom.style.width = "100px";
			}
			this.tb.doLayout();
		} else {
			this.tb.hide();
		}

		h +=  this.tb.el.getHeight();

		if(this.growMaxHeight && h > this.growMaxHeight) {
			h = this.growMaxHeight;
		}

		this.setHeight(h);

	},

//	correctify: function(full, prefix, letter){
//		var regex = /([:\?]\s+)(.)/g;
//		return prefix + letter.toUpperCase();
//	},

//	urlify : function () {
//		
//		var inputText = this.getEditorBody().innerHTML;
//		var replacedText, replacePattern1, replacePattern2, replacePattern3;
//
////		//URLs starting with http://, https://, or ftp://
////		replacePattern1 = /(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/gim;
////		replacedText = inputText.replace(replacePattern1, '<a href="$1" target="_blank">$1</a>');
////
////		//URLs starting with "www." (without // before it, or it'd re-link the ones done above).
////		replacePattern2 = /(^|[^\/])(www\.[\S]+(\b|$))/gim;
////		replacedText = replacedText.replace(replacePattern2, '$1<a href="http://$2" target="_blank">$2</a>');
////
////		//Change email addresses to mailto:: links.
////		replacePattern3 = /(\w+@[a-zA-Z_]+?\.[a-zA-Z]{2,6})/gim;
////		replacedText = replacedText.replace(replacePattern3, '<a href="mailto:$1">$1</a>');
//
//	replacedText = inputText.replace(/(?:^|[^\>])((ftp|http|https|file):\/\/[\S]+(\b|$))/gim, '<a href="http://$1">$1</a>');
//
//		
//		this.getEditorBody().innerHTML=replacedText;
//		
//		console.log(this.getEditorBody().innerHTML);
//		
//	},
	onFirstFocus: function () {

		this.initPunctuationCorrection();

		this.activated = true;
		this.disableItems(this.readOnly);
		if (Ext.isGecko) { // prevent silly gecko errors
			/*this.win.focus();
			 var s = this.win.getSelection();
			 if(!s.focusNode || s.focusNode.nodeType != 3){
			 var r = s.getRangeAt(0);
			 r.selectNodeContents(this.getEditorBody());
			 r.collapse(true);
			 this.deferFocus();
			 }*/
			try {
				this.execCmd('useCSS', true);
				this.execCmd('styleWithCSS', false);
				this.execCmd('insertBrOnReturn', false);
				this.execCmd('enableObjectResizing', true);
			} catch (e) {
				console.warn(e);
			}
		}
		this.fireEvent('activate', this);
	},
	createToolbar: Ext.form.HtmlEditor.prototype.createToolbar.createSequence(function (editor) {
		this.tb.enableOverflow = true;
	}),

	initPunctuationCorrection: function () {
		if (GO.settings.auto_punctuation != 1)
			return;

		var me = this;
		var doc = me.getDoc();

		Ext.EventManager.on(doc, 'keydown', me.correctPunctuation, me);
	},

	lastKey: false,
	capNext: true,

	correctPunctuation: function (ev) {
		ev = ev.browserEvent;
		if (!this.capNext) {
			if(ev.key === 'Enter' || (ev.key === ' ' && ['.', '!', '?'].indexOf(this.lastKey) !== -1)) {
				this.capNext = true;
			}
		} else {
			if (ev.key.match(/^[a-z]$/)) {
				ev.preventDefault();
				this.insertAtCursor(ev.key.toUpperCase());
				this.capNext = false;
			} else if (ev.key != ' ' && ev.key != 'Enter') {
				this.capNext = false;
			}
		}
		this.lastKey = ev.key;
	},

	// getFontStyle :  function() {
	// 	var style = getComputedStyle(this.getEl().dom);
	// 	return "font-size: " + style['font-size'] + ';font-family: '+style['font-family'];
	// },
	//
	// getEditorFrameStyle : function() {
	// 	return 'body,p,td,div,span{' + this.getFontStyle() + '};body{border: 0; margin: 0; padding: {0}px; height: {1}px; cursor: text}body p{margin:0px;}';
	// },
	//
	// getDocMarkup: function () {
	// 	console.warn( this.getEditorFrameStyle());
	// 	var h = Ext.fly(this.iframe).getHeight() - this.iframePad * 2;
	// 	return String.format('<html><head><meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" /><style type="text/css">' + this.getEditorFrameStyle() + '</style></head><body></body></html>', this.iframePad, h);
	// },
	fixKeys: function (e) { // load time branching for fastest keydown performance

				var k = e.getKey(), doc;
				if(
					Ext.isWebKit && e.shiftKey && k == e.ENTER && (doc = this.getDoc()) &&
					(doc.queryCommandState('insertorderedlist') || doc.queryCommandState('insertunorderedlist'))
				) {
					e.stopEvent();
					this.execCmd('InsertHtml',Ext.isGecko ? '<br />' : '<br /><br />');
					this.deferFocus();
				} else if (k == e.TAB) {
					e.preventDefault();
					doc = this.getDoc();
					if (doc.queryCommandState('insertorderedlist') || doc.queryCommandState('insertunorderedlist')) {
						this.execCmd(e.shiftKey ? 'outdent' : 'indent');
					}else {
						this.execCmd('InsertText', '\t');
					}
					this.deferFocus();
				}


	},

	//Overwritten to fix font size bug in chrome
	adjustFont: function (btn) {
		var adjust = btn.getItemId() == 'increasefontsize' ? 1 : -1,
						doc = this.getDoc(),
						v = parseInt(doc.queryCommandValue('FontSize') || 2, 10);
		if (Ext.isAir) {


			if (v <= 10) {
				v = 1 + adjust;
			} else if (v <= 13) {
				v = 2 + adjust;
			} else if (v <= 16) {
				v = 3 + adjust;
			} else if (v <= 18) {
				v = 4 + adjust;
			} else if (v <= 24) {
				v = 5 + adjust;
			} else {
				v = 6 + adjust;
			}
			v = v.constrain(1, 6);
		} else {
			v = Math.max(1, v + adjust);
		}
		this.execCmd('FontSize', v);
	},

	// private
//	onEditorEvent : function(e){
//		this.updateToolbar();
////		console.log(e);
//		
//		if(e.keyCode==32 || e.keyCode==12)
//			this.urlify();
//	},


	createLink: function () {


		var url = prompt(this.createLinkText, this.defaultLinkValue);
		if (url && url != 'http:/' + '/') {
			if (Ext.isSafari) {
				this.execCmd("createlink", url);
				this.updateToolbar();
			} else
			{
				this.relayCmd("createlink", url);
			}
		}
	},

	setDesignMode : function(readOnly){
		this.getEditorBody().contentEditable = readOnly;
   },
	
	onResize : function(w, h){
	  Ext.form.HtmlEditor.superclass.onResize.apply(this, arguments);
	  if(this.el && this.iframe){
			if(Ext.isNumber(w)){
				 var aw = w - this.wrap.getFrameWidth('lr');
				 this.el.setWidth(aw);
				 this.tb.setWidth(aw);
				 this.iframe.style.width = Math.max(aw, 0) + 'px';
			}
			if(Ext.isNumber(h)){
				 var ah = h - this.wrap.getFrameWidth('tb') - this.tb.el.getHeight();
				 this.el.setHeight(ah);
				 this.iframe.style.height = Math.max(ah, 0) + 'px';
				 var bd = this.getEditorBody();
				 if(bd){
					 bd.style.height = Math.max((ah - (this.iframePad*2)), 0) + 'px';
				 }
			}
		}
   }

});

Ext.reg('xhtmleditor', GO.form.HtmlEditor);
