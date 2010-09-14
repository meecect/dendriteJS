/*****************************************************************************
 * jQuery Finder - Makes lists into a Finder, similar to Mac OS X
 * 
 * @version		0.3a (ALPHA Version - not extensively tested, not tested at all on IE!)
 * @date		08 Dec. 2008
 * @author		Nicolas Rudas
 * @url			nicolas.rudas.info/jquery/finder/
 * @copy		Nicolas Rudas - MIT Licensed	
 *
 *****************************************************************************
 * Syntax:
 *		$(selector).finder()					Create a new finder with default options
 *
 *		$(selector).finder(options)				Create a new finder with additional options
 *
 *		$(selector).finder(method,[arguments])	Execute a method on an existing finder
 *												-	select			Select item
 *												-	refresh			Reload currently selected item (cache is ignored)
 *												-	destroy			Completely remove finder

 *
 *		$(selector).finder(method,callback)		Overide a method (setter)
 *												-	onItemSelect	Callback after item is selected
 *												- 	onFolderSelect	Callback after folder is selected
 *												
 **/
;(function($){
$.fn.finder = function(o,m){
// Default options
	var defaults = {
		url : false, 					/*	{String}		Fetch initial list via ajax		*/
		onRootReady  : function(){},	/*	{Function}		Callback after initial list retrieved via ajax	*/
		onItemSelect : function(){},	/*	{Function}		Callback after user clicked on item's list item	*/
		onItemOpen : function(){},		/*	{Function}		Callback after user clicked on item's anchor	*/
		onFolderSelect : function(){},	/*	{Function}		Callback after user clicked on folder's list item	*/
		onFolderOpen : function(){},	/*	{Function}		Callback after user clicked on fodler's anchor	*/
		animate : true,					/*	{Boolean}		Slide in new columns 		*/
		cache : false,					/*	{Boolean}		Do not request via ajax, if such data exist		*/
		ajax : { cache : false },		/*	{Boolean}		Cache ajax requests (append timestamp to url)	*/
		listSelector : false,			/*	{String|Boolean}jQuery selector for list items					*/
		wrapper : '<div class="finder finder-wrapper"><div class="finder-wrapper-inner"></div></div>',
		listWrapper : '<div class="finder-list-wrapper"></div>'
	};
	
// Keep a reference to all finders created
	var Finders = $.Finders = $.Finders || {};
	
// Return a new timestamp
// Usually used for caching URLs, or creating unique identifiers e.g. Finders[ timestamp() ] = new Finder();
	var timestamp = function() {  return parseInt(new Date().valueOf()); };
	
// Check if scrollTo Plugin exists
	var scrollToPlugin = $.scrollTo || false;
	if(typeof scrollToPlugin == 'function') {
		scrollToPlugin = true;
		$.scrollTo.defaults.axis = 'xy';
		$.scrollTo.defaults.duration = 900;
	} 
		
// Internal debugging
	if(!window.debug) {
		window.debug = function(){
			var msg = ($.browser.mozilla)
						? arguments
						: $.makeArray(arguments).join(', ');
			
			if(typeof console == 'object') {
				console.log(msg);
				return;
			}
		//	alert(msg);
		};
	}
	
// Set some variables (know what we are dealing with)
	var method, opts,
		url = (typeof m == 'string') ? m : null,
		func = (typeof m == 'function') ? m : null,
		_args = arguments;
		
	if(typeof o == 'string') { method = o; }
	else if (typeof o == 'object')  { opts = o; }
		
	if(opts) { opts = jQuery.extend(defaults, opts);}
	else { opts = defaults;}
	
	/**
	 * Finder Constructor
	 * 
	 *
	 **/
	function Finder(element,finderId){
		this.cache = {};
		
		this._queue = [];
		
		this.settings = {};
		
		this.id = finderId;
		
	// Reference to initial element - used when destroying Finder
		this.initial = $(element).clone(true);
		
	// Reference to element, used throughout
		this.element = $(element);
		this.element.attr('data-finder-ts',this.id);

	// make options internal properties
		var that = this;
		for(var i in opts){	that.settings[i] = opts[i];	}
		
		return this;
	};
	
	/**
	 * Initialise Finder
	 * Append necessary HTML, bind events etc
	 *
	 **/
	 Finder.prototype.init = function(){
		var that = this;
		
		var wrapper = $(this.settings.wrapper);
				
	// Wrap list to finder-wrapper
		this.element.wrap(wrapper);
		this.wrapper = this.element.parents('.finder-wrapper-inner');
	
	// Bind click events to wrapper so that only two events per finder are specified
		this.wrapper
			.unbind('click.FinderSelect') // Click event to handle showing of columns etc
			.bind('click.FinderSelect',function(e){
				var event_target = e.target,
					$event_target = $(event_target);
				
			// If target element is not a list item, not an anchor
			// and not in a Finder-related list	(.finder-type-folder)
			// we do not care
				if( event_target.nodeName != 'LI'
				   && event_target.nodeName != 'A'
				   || $event_target.parents('.finder-type-folder').length === 0 )
				{ return; }
			
			// Otherwise 'register' this action in queue 
				that.queue($event_target);
			
			// And prevent any other browser actions
				return false;
			})
			.unbind('click.FinderPreview') // Click event to handle file previews etc
			.bind('click.FinderPreview',function(e){
				var title = $(e.target);
				
				if( !title.hasClass('preview') && title.parent('.preview').length === 0 )
					{ return; }
				
				title = ( title.hasClass('preview') )
							? title
							: title.parent('.preview') ;
				
				var image = title.siblings('.image');
				
				if( image.length != 1  ) { return; }
				
				if(image.is(':visible')) {
					image.slideUp();
					title.addClass('closed');
				} else {
					image.slideDown();
					title.removeClass('closed');
				};
				
				return false;
			});
	
	// Initialise root list
		this.selectFolder('root');
		
		return this;	
	};
	
	/**
	 * Queue - Following a click event on a list item or anchor, the queue function is called
	 * It stores info about click events so that the script can handle click events
	 * on a first-come first-served basis.
	 *
	 * @param	noCache	- True when queue function called via 'refresh' API
	 *						i.e. caching is false when refreshing 
	 * @param	actionType	- Either 'select' or 'open', specified only if queue fn
	 *							called via API (ie. selector.finder('select', ... ))
	 **/
	Finder.prototype.queue = function(target,noCache,actionType /* select or open */){
		var	that = this,
			wrapper = this.wrapper;
		
		this._queue.push( [target,noCache,actionType] );
		
	// isProcessing is set to true when the Finder is currently 'doing stuff'
	// and set to false when not. So, if its not doing anything right now,
	// continue to process this event
		if(!this.isProcessing) { this.preSelect(); }
		
		return this;
	};
	
	/**
	 * preSelect - Simple function to determine which item to select
	 * based on the current queue => Always first item in queue
	 * (first-come, first-served)
	 **/
	Finder.prototype.preSelect = function(){
		var q = this._queue;
		
		if(q.length==0) { return;}
		
		this.select.apply(this,q[0]);
		
		return this;
	};
	
	/**
	 * Select Item - Considering the target of a click event, this function determines
	 * what to do next by taking into consideration if target was anchor, or list item,
	 * and if target was a file or a folder.
	 * 
	 * Note:	- Cannot select an item which is not in page (i.e. in sublevels)
	 *			- When selecting item via API, not selecting levels properly
	 **/
	Finder.prototype.select = function(target,noCache,actionType) { //targetElement,targetA
		var that = this,
			wrapper = this.wrapper,
			targetElement = (typeof target == 'object') ? $(target) : $('a[rel="'+target+'"]',wrapper);

		if(typeof target.length != 'number') { debug('Target must be either a URL or a jQuery/DOM element'); return false; }
		if(!targetElement[0]) { debug('Target element does not exist',target); return false; }
		
		this.isProcessing = true;
				
		var	targetA = (targetElement[0].nodeName == 'A') ? targetElement : $('a',targetElement),
			targetURL = targetA.attr('rel');
		
		var	targetList = (targetElement[0].nodeName == 'LI') ? targetElement : targetElement.parent('li'),
			targetContainer =  targetList.parents('[data-finder-list-level]:first'),
			targetLevel = targetContainer.attr('data-finder-list-level'),
			type = (targetList.hasClass('file')) ? 'file' : 'folder',
			url = targetA.attr('rel'),
			wrapperLists = $('.finder-list-wrapper:visible',wrapper);
		
	// If select was triggered via API and target was a URL (e.g. finder('select',url))
	// then target is considered to be the list item so as to select item and not open it.
	// This allows user to select an item by providing the URL of an anchor element
	// which would otherwise open the item
		if(actionType == 'select' && typeof target == 'string' && type == 'file') {
			targetElement = targetList;	}		
		
	// Currently selected item will no longer be active
		$('.activeNow',wrapper).removeClass('activeNow');
	
	// Remove visible lists which should not be visible anymore
		wrapperLists.each(function(){		
			var finderListWrapper = $(this),
				finderListLevel = finderListWrapper.attr('data-finder-list-level');
		
			if( finderListLevel >= targetLevel ) {
				$('.active',finderListWrapper).removeClass('active'); 	}
			
			if( finderListLevel > targetLevel ) {
				finderListWrapper.remove();	}
		});
				
	// Style selected list item
		// active refers to all previously selected list items
		// activeNow refers to the currently active list item
		targetList
			.addClass('active')
			.addClass('activeNow');
	
	// Scroll to selected item
	// Mostly useful if item not selected following direct user action (e.g. click event) 
		if(scrollToPlugin){
			targetContainer.scrollTo(targetList); }
	
	// Call onSelectItem or onSelectFolder callbacks
	// If callback does not return false,
	// proceed to display item/folder in new column
		if (type == 'file') {			
			var selectItemCallback = that.selectItemCallback(targetA);
			if( selectItemCallback !== false ) {
				
			// Notify user of loading action	
				targetList.addClass('loading');
				
			// Select item	
				that.selectItem(url,targetElement,noCache);
			
				return this;
			}
		}
		else {
			var selectFolderCallback = that.selectFolderCallback(targetA);
			if( selectFolderCallback !== false ) {
				
			// Notify user of loading action	
				targetList.addClass('loading');
				
			// Select folder	
				that.selectFolder(url,targetElement,noCache);
			
				return this;
			}
		}

	// Script will only reach this point when select callbacks return false	
		
	// Adjust the width of the current columns
	// true param needed so that adjustWidth knows that
	// there are no new columns being added	
		this.adjustWidth(true);

	// Finalise process (move on with queue etc)
		this.finalise();
	
		return this;
	};
	
	/**
 	 * Select Item - When selecting items as opposed to folders
	 **/	
	Finder.prototype.selectItem = function(url,target,noCache){
		/* Display item info */
		var that = this,
			data, wrapper;
			
		var appendNewColumn = function(){
			that.appendNewColumn(url,data,target,'file');
		};
		
		if( typeof this.cache[url] == 'object' && this.settings.cache && !noCache) {
			if(this.cache[url].status == 'success' ) {
				data  = this.cache[url].data;
				appendNewColumn();
			}
		} else {
		
			$.ajax({
				url : url,
				cache : that.settings.ajax.cache,
				success : function(d){
					data = $('<div></div>').append(d);
					that.cache[url] = { 'url':url, 'data' : data, 'date': new Date().valueOf(), 'status' : 'success' };
				},
				complete : function(){
					appendNewColumn();
				}
			});
		}

		
		return this;
	};
	
	/**
 	 * Select Folder - When selecting folders as opposed to items
	 **/
	Finder.prototype.selectFolder = function(url,target,noCache){
		var that = this,
			list = (url == 'root')
						? (this.settings.url) ? null : this.element
						: null,
			url = (url == 'root' && typeof this.settings.url == 'string') ? this.settings.url : url,
			wrapper;

		this.lastFolder = target;

		var appendNewColumn = function(){
			that.appendNewColumn(url,list,target,'folder');
			appendClasses();
		};

	// Append classes if we know what each item stands for (item or folder)
	// i.e. when not loading next level via ajax
	// ** NOT supported
		var appendClasses = function(){

			$('li',list).each(function(){

			// Remove links
				var anch = $('a',this),
					anchHref = anch.attr('href');

				if(anch.attr('rel') == anchHref.substring(1)) { return;}

				anch
					.attr('rel',anchHref)
					.attr('href','#'+anchHref);

				if(anch.attr('title').length == 0) {anch.attr('title',anchHref);}	
			});
		};

	// Folder contents exist	
		if(list && !noCache) {	appendNewColumn();	}
		else if( typeof this.cache[url] == 'object' && this.settings.cache && !noCache) {
			if(this.cache[url].status == 'success' ) {
				list = this.cache[url].data;
				if(url === that.settings.url && typeof that.settings.onRootReady == 'function') {
					that.onRootReady.call(that,list,response);
				}
				appendNewColumn();
			}
		}
	// Get folder contents from URL	
		else {
			$.ajax({
				url : url,
				cache : that.settings.ajax.cache,
				success: function(response){
					var d = $('<div></div>').append(response);
					if(that.settings.listSelector) {
						list = $(that.settings.listSelector,d);
						if(list.length == 0) {	list = d;}
					}
					else {	list = d;}
					that.cache[url] = {
						'url':url, 'data' : list, 'response': response, 'date': new Date().valueOf(), 'status' : 'success'
					};
				},
				complete : function(){
					if(url ===  that.settings.url && typeof that.settings.onRootReady == 'function') {
						that.onRootReady.call(that,list,$(that.cache[url].response));
					}

					appendNewColumn();
				}
			});

		}

		return this;
	};
	
	/***
 	 * Append new Column - Function to append a new column to the finder
	 * called from either selectItem or selectFolder functions
	 * 
	 * Triggers Callback functions for OpenItem or OpenFolder !
	 ***/	
	Finder.prototype.appendNewColumn = function(url,data,target,type){
		var that = this,
			targetParent = $('a[rel="'+url+'"]',that.wrapper).parents('[data-finder-list-level]'),
			columnId = url.replace(/[\W\s]*/g,''),
			columnLevel = (function(){
				if (url == that.settings.url || url == 'root') { return 0; }
				return parseInt(targetParent.attr('data-finder-list-level')) + 1;
			})(),
			wrapper,
			wrapperType = type;
			
	// If column already exists, remove it
		var newColumn = $('[data-finder-list-id="'+columnId+'"]');
		if(newColumn.length > 0) { newColumn[0].parentNode.removeChild(newColumn[0]); }
		
	// Specify new column, and add necessary attributes
		wrapper = $(this.settings.listWrapper);
		wrapper.addClass('finder-type-'+type);
		wrapper.attr('data-finder-list-id',columnId);
		wrapper.attr('data-finder-list-source',url);
		wrapper.attr('data-finder-list-level',columnLevel);		
		wrapper.css('z-index',0);	
	
	// Append new column
	// Plain DOM scripting used as opposed to jQuery as it's faster
		this.wrapper[0].appendChild(wrapper[0]);
			// instead of:
			//	wrapper.appendTo(this.wrapper);
		
	// Append data to new column
		var finderContents = document.createElement('div');
		finderContents.className = 'finder-contents';
		finderContents.appendChild($(data)[0]);
		wrapper[0].appendChild(finderContents);
			// instead of:
				// wrapper.append($('<div class="finder-contents"></div>').append(data));
		
	// Adjust the width of the Finder
	// but make sure that column is appended & parsed (timeout = 0)		
		setTimeout(function(){
			that.adjustWidth();},0);

	// Call onOpenItem or onOpenFolder callback if target was anchor
	// Note: target check necessary, root list has no target
		if(target && target[0]) {
			if(target[0].nodeName == 'A') {
				if(type == 'file') { that.openItemCallback(target,wrapper); }
				else { that.openFolderCallback(target,wrapper); }
			}
		}

		return this;	
	};
	
	/***
 	 * Adjust Width - Adjust the width of the columns and the wrapper element
	 * param ignoreNew is true when select callbacks return false
	 ***/	
	Finder.prototype.adjustWidth = function(ignoreNew){
		var that = this,
			wrapper = this.wrapper,		
			newColumn = $('[data-finder-list-id]:visible:last',wrapper);
			
	// Get all siblings of the new column
	// i.e those visible and not the last, as new column is always last 	
		var columns = wrapper.children('[data-finder-list-id]:visible:not(:last)'),
			width = 0;
			
	// Prevent previous columns from taking up all the space (width)
	// Right property of columns MUST NOT BE SET IN CSS!!
	// Only the last column's right property must be set to 0
		columns.css('right','');
			
	// Calculate the space taken by the visible columns
	// The total width  of these columns will be set as 
	// the left property of the new column (so that each column appears next to each other)
		columns.each(function() {			
			width += $(this).outerWidth({margin:true});	});
		
	// Need to know the width of the new column (newColumnWidth),
	// the total width of all columns (newWidth),
	// the current width of the wrapper element (currentWidth),
	// and the available width (specified in wrapper's parent)
		var newColumnWidth = newColumn.outerWidth({margin:true}),
			newWidth =  width + newColumnWidth,
			currentWidth = wrapper.innerWidth(),
			availableWidth = wrapper.parent().innerWidth();
		
	// Adjust the width of the wrapper element. As columns as absolutely positioned
	// no horizontal scrollbars appear if the total width of the columns exceeds the space available.
	// By setting the width of the wrapper element to that of the columns, a horizontal scrollbar appears.
		if ( newWidth > availableWidth || newWidth < currentWidth && currentWidth > availableWidth && newWidth != currentWidth) {
			
		// If going from multiple levels down (ie. many columns) to a higher level
		// (ie. to few columns) the new width will be less than available.
		// Also if theres only one column visible (ie. root) newWidth will equal newColumnWidth.
		// In these cases make sure Finder takes up all available space.			
			if(newColumnWidth == newWidth || newWidth < availableWidth) { newWidth = 'auto'; }
		
		// Set width to new
			wrapper.width( newWidth ); }
		
	// Make the new column take up all available space
	// this must be set AFTER new column's width has been retrieved
	// otherwise the value is not true
		newColumn.css('right',0);
		
	// As the column is absolutely positioned, its left property
	// must be specified	
		newColumn.css('left',width);
	
	// By setting the z-index of the new column to '2'
	// it prevents subsequent columns (children) from being above it
	// whilst their css properties (left & right) are being set.
	// For this to be effective columns must have a background specified
	// (CSS class: .finder-list-wrapper)
		newColumn.css('z-index',2);
	
	// Scroll to new column
		if(scrollToPlugin){
			wrapper.parent().scrollTo(newColumn); }
	
	// ignoreNew exists when select callbacks return FALSE
	// i.e. no new column was appended, but width of existing columns
	// and wrapper still need fixing
		if(!ignoreNew) { 
			that.displayNewColumn(newColumn); }
		
		return this;
	};
	
	Finder.prototype.displayNewColumn = function(newColumn) {
		var that = this,
			wrapper = this.wrapper;
					
	// Animate column if desired
		if(this.settings.animate) {
		// To animate the column we cannot use its width value (its 0)
		// but we can use its left property to calculate the width it currently occupies.
	 	// Pixels from left - total pixels = pixels available for the column (i.e. width)
			var fromLeft = newColumn.css('left').replace(/\D/g,''),
				fromRight = wrapper.width() - fromLeft;
		
		// So by setting the column's right property to the calculated value
		// and keeping its left property, the column becomes insivible
		// The animation then decreases the right property gradually to zero
		// to make the column visible
			newColumn
				.css('overflow-y','hidden') // avoid showing a scroll bar whilst animating
				.css('right',fromRight)
				.animate({'right':0 },{
					duration:500,
					complete:function(){
						newColumn.css('overflow-y','scroll');
						that.finalise(); }
				});
				 
		} else {	
			that.finalise();  }
		
		return this;
	};
	
	Finder.prototype.finalise = function(){
	// Remove any loading classes	
		$('.finder-type-folder li.loading').removeClass('loading');
		
	// Specify that script is done processing
	// (used in queing) 
		this.isProcessing = false;

	// Remove last item from queue
	// and if there are more items, move on
		this._queue.shift();
		if(this._queue.length > 0) { this.preSelect(); }
		
		return this;
	};
	
	Finder.prototype.destroy = function(){
	// Unbind events	
		this.wrapper
			.unbind('click.FinderSelect')
			.unbind('click.FinderPreview');
	
	// Remove Finder's HTML, append initial element
		this.element.parents('.finder-wrapper').replaceWith(this.initial);
	
	// Delete reference to Finder
		delete Finders[this.id];
		
		return this;
	};
	
	Finder.prototype.current = function(){
		var current = $('.activeNow',this.wrapper).find('a');
		return (current.length>0) ? current : null;
	};

	Finder.prototype.refresh = function(){
		var current = this.current();
		
		if(current) {	this.queue(current,true);	}
		else {	this.selectFolder('root',true);	}
		
		return this;
	};
	
	Finder.prototype.selectItemCallback = function(){
	/* Do something after item is selected */
		var callback = this.settings.onItemSelect,
			args = arguments;
		
		if(!args[0]) { return debug('No target defined'); }
		if(typeof callback == 'function') {	return callback.apply(this,args);	}
		
		return this;
	};
		
	Finder.prototype.selectFolderCallback = function(){
	/* Do something after folder is selected */
		var callback = this.settings.onFolderSelect,
			args = arguments;
		
		if(!args[0]) { return debug('No target defined'); }
		if(typeof callback == 'function') {	return callback.apply(this,args);	}
		
		return this;
	};
	
	Finder.prototype.openItemCallback = function(){
	/* Do something after item is selected */
		var callback = this.settings.onItemOpen,
			args = arguments;
		
		if(!args[0]) { return debug('No target defined'); }
		if(typeof callback == 'function') {	callback.apply(this,args);	}
		
		return this;
	};
	
	Finder.prototype.openFolderCallback = function(){
	/* Do something after item is selected */
		var callback = this.settings.onFolderOpen,
			args = arguments;
		
		if(!args[0]) { return debug('No target defined'); }
		if(typeof callback == 'function') {	callback.apply(this,args);	}
		
		return this;
	};
	
	
	var _finder = Finders[ $(this).attr('data-finder-ts') ];
	
	if(method == 'current' && _finder) { return _finder.current();	}
	
	return this.each(function(){
		var finderId = $(this).attr('data-finder-ts') || null,
			timeStamp = new Date().valueOf();
			
	// If name of method provided
	// execute method
		if(finderId && method) {
			var finder = Finders[finderId];
			
		// Access private methods	
			if(method == 'select' && m) {
				if(m.constructor == Array) {
					m = m.reverse();
					for (var i = m.length - 1; i >= 0; i--){
						finder.queue(m[i],false,method); }
				}
				else { finder.queue(m,false,method); }
			}
			
			else if(method == 'adjustWidth') {	finder.adjustWidth();	}
			
			else if(method == 'destroy') {	finder.destroy();	}
			
			else if(method == 'refresh') {	finder.refresh();	}
			
		// Access callbacks
			else if(method == 'onItemSelect') {
			// If argument passed is function, act as a setter	
				if(func) { finder[method] = m; }
			// Otherwise execute
				else { finder.selectItemCallback(m); }
			}
			else if(method == 'onFolderSelect') {
			// If argument passed is function, act as a setter	
				if(func) { finder[method] = m; }
			// Otherwise execute
				else { finder.selectFolderCallback(m); }
			}
			
		}
	// If no method provided
	// new finder is created
		else if (!method) {	Finders[timeStamp] = new Finder(this,timeStamp).init();	} 
		
		else if (!finderId && method) {	debug('Element is not a finder');	}
		
	});
};})(jQuery);