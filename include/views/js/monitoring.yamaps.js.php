<script type="text/javascript">
var ZabbixYaMapRO = Class.create(ZabbixYaMap, {
	/* Add variables */
	minseverity  : 0,
	HostArray    : undefined, // Cluster of geo objects
	ProblemArray : undefined,

        LinksChange :   [],     // Contains the changed edges of links
	Hosts :   [],
	CurrentSelectGroup: -100,

	ShowGroup: false,	
	AlwaysShowAllHostGroups : false,  // Показывать полную карту хостов независимо от выбранной группы
	/* Add new methods */
	/**
	 * Initialization of additional controls
	 */
	init: function() {
		var me = this;
		//console.log('inside ZabbixYaMapRO.init()');
                //<--san
                me.LinksArray = new ymaps.GeoObjectArray({},{mylink:1});
		// -->
		me.HostArray = new ymaps.Clusterer({
			maxZoom : 17,
			clusterDisableClickZoom: true,
			preset: 'twirl#greenClusterIcons'
		});
		//me.ProblemArray = new ymaps.GeoObjectCollection();
		me.ProblemArray = new ymaps.Clusterer({ 
			maxZoom: 17,
			clusterDisableClickZoom: true,
			preset: 'twirl#redClusterIcons'
		});

		me.SetSelect(document.getElementById("selectgroup"), "<?php echo _('All'); ?>", "<?php echo _('All'); ?>");

		/* Display the problems */
	//	me.ChangeGroup();
	//	me.problems();

		var interval = setInterval(function() {
			me.problems();
		}, 60000);

		var UpdateListBox = new ymaps.control.ListBox({
			data : {
				title : '<?php echo _('refreshed every'); ?> 60 <?php echo _('sec'); ?>'
			},
			items : [ 
			new ymaps.control.ListBoxItem({
				data : {
					time : 10,
					content : '10 <?php echo _('sec'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					time : 30,
					content : '30 <?php echo _('sec'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					time : 60,
					content : '60 <?php echo _('sec'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					time : 120,
					content : '120 <?php echo _('sec'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					time : 600,
					content : '600 <?php echo _('sec'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					time : 900,
					content : '900 <?php echo _('sec'); ?>'
				}
			}), 
			]
		}, {
			position : {
				top : 5,
				right : 100
			}
		});
		for ( var i = 0; i < UpdateListBox.length(); i++) {
			(function(i) {
				UpdateListBox.get(i).events.add('click', function() {
					clearInterval(interval);
					interval = setInterval(function() {
						me.problems();
					}, UpdateListBox.get(i).data.get('time') * 1000);
					UpdateListBox.collapse();
					UpdateListBox.setTitle('<?php echo _('refreshed every'); ?> '
							+ UpdateListBox.get(i).data.get('time') + ' <?php echo _('sec'); ?>');
				});
			})(i);
		}

		me.Map.controls.add(UpdateListBox);
		
		
		var MinseverityListBox = new ymaps.control.ListBox({
			data : {
				title : '<?php echo _('Show all events'); ?>'
			},
			items : [ new ymaps.control.ListBoxItem({
				data : {
					severity : 0,
					content : '<?php echo _('Not classified'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					severity : 1,
					content : '<?php echo _('Information'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					severity : 2,
					content : '<?php echo _('Warning'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					severity : 3,
					content : '<?php echo _('Average'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					severity : 4,
					content : '<?php echo _('High'); ?>'
				}
			}), new ymaps.control.ListBoxItem({
				data : {
					severity : 5,
					content : '<?php echo _('Disaster'); ?>'
				}
			}) ]
		}, {
			position : {
				top : 5,
				right : 720
			}
		});
		for ( var i = 0; i < MinseverityListBox.length(); i++) {
			(function(i) {
				MinseverityListBox.get(i).events.add('click', function() {
					/* Setting up the minimum severity */
					me.minseverity = MinseverityListBox.get(i).data.get('severity');
					//console.log('The min severity is: '+ minseverity);
					MinseverityListBox.collapse();
					MinseverityListBox.setTitle('<?php echo _('Show'); ?> '
							+ MinseverityListBox.get(i).data.get('content')
							+ ' <?php echo _('and more'); ?>');
				});
			})(i);
		}
		me.Map.controls.add(MinseverityListBox);
		
		var FollowProblem = new ymaps.control.RadioGroup({
			items : [ new ymaps.control.Button('<?php echo _('Follow the events'); ?>'),
					new ymaps.control.Button('<?php echo _('Follow the chosen group'); ?>') ]
		}, {
			position : {
				top : 5,
				right : 350
			}
		});
		FollowProblem.get(0).select();
		FollowProblem.get(0).events.add('click', function() {
			//console.log('Setting ZabbixYaMap.PrioProblem to true');
			me.PrioProblem = 'true';
		});
		FollowProblem.get(1).events.add('click', function() {
			//console.log('Setting ZabbixYaMap.PrioProblem to false');
			me.PrioProblem = 'false';
		});
		me.Map.controls.add(FollowProblem);
		
		//ChangeGroup();
		// Set up onChange reaction
		jQuery('#selectgroup').change(function() {
			//me.problems();
			me.ChangeGroup();


		});
		//<--san
		me.ShowGroupButton  = new ymaps.control.Button({
			data : {
				content : 'Показать Хосты',
				title : 'Нажмите, чтобы показать все хосты группы'
			}
		}, {
			position : {
				top : 35,
				left : 5
			},
			selectOnClick : true
		});	
		me.ShowGroupButton.events.add('click', function() {
		    var state=me.ShowGroupButton.isSelected();
		    var sel = document.getElementById("selectgroup");
		    if (state==true){   // отжата	
			me.ShowGroup=false;
			}
		    else{
			 me.ShowGroup=true;	
			}
		    me.ChangeGroup();

	        });
		 me.ShowGroupButton.deselect();
		me.Map.controls.add(me.ShowGroupButton);
		//san-->


          /* Display the problems */
                me.ChangeGroup();
               // me.problems();


	},



          /* Export from  patch  */
	  genLinks:function(hostid) { 
		var links = '<br><a target="Result" href="latest.php?hostid=' + hostid +'">last values</a>' +
			'<br><a target="Result" href="hosts.php?form=update&groupid=0&hostid=' + hostid +'">host config</a>' +
			'<br><a target="Result" href="items.php?groupid=0&hostid=' + hostid +'">item config</a>'+
			'<br><a target="Result" href="graphs.php?groupid=0&hostid=' + hostid +'">graphs config</a>'+
			'<br><a target="Result" href="triggers.php?groupid=0&hostid=' + hostid +'">trigger config</a>';
		return links;
	    },

	  genLinks_script:function(hostid,scriptid,scriptname) { 
		var links = '\n<br><a href="javascript:window.open(\'/scripts_exec.php?execute=1&hostid=' 
								    + hostid +'&scriptid='+scriptid+'&sid='+this.get_sid()
								    +'\',\'NEWWIN\', \'left=30,top=30,width=600,height=400\')" >'+scriptname+'</a>';
		return links;
	    },

         AddLinksScript:function(hostIds){
         var me=this;

         var ScriptsQuery= {
                           jsonrpc: "2.0",
                           method: "script.getscriptsbyhosts",
                           params: hostIds,
                           id: 1000009
                           }; 
           //   console.log(ScriptsQuery);
              me.apiQuery(ScriptsQuery, true, function(data1){
            //    console.log(data1);
            //    console.log('Length Hosts='+me.Hosts.length);
		for (var i=0;i<me.Hosts.length;i++){
                   var Host=me.Hosts[i];
                   var hostid=Host.properties.get('hostid');
		   var balloonContent=Host.properties.get('balloonContent');
		    balloonContent=balloonContent+'<br>'+me.genLinks(hostid);
        	    Host.properties.set('balloonContent',balloonContent);
	       	    Host.properties.set('fill_ballon',true);
                   var san_links='';
                   var myarr=data1.result[hostid];
                   if (jQuery.isArray(myarr)){  
                           for (var k = 0; k < myarr.length; k++){      
                                san_links=san_links+' '+me.genLinks_script(hostid,myarr[k].scriptid,myarr[k].name);
                           }
                   }
                   if(san_links.length>0){
                          Host.properties.set('balloonContent',balloonContent+'<br><br>Скрипты<br>'+san_links);

                        // console.log('i='+i+'  ==>'+Host.properties.get('balloonContent'));    
                   }   
	//	console.log(Host) ;
		}//for
             },'Cannot load Scripts by host');
                //      console.log(''+hostid);
   },

	    
	  get_sid: function(){
	   var str=this.auth();
	   return str.substr(16,16);
	  },
	/**
	 * Displays the problems
	 */
	problems: function() {
		var me = this;
		var HostsIds=[];
		me.Map.geoObjects.remove(me.ProblemArray);
       	        me.ProblemArray.removeAll();
                for (var i=0;i<me.Hosts.length;i++){HostsIds[i]=me.Hosts[i].properties.get('hostid');}
		var sel = document.getElementById("selectgroup");
		var groupid = sel.options[sel.selectedIndex].value;
		// if groupid=0, do not add it to the query
		if(groupid == 0){
			var groups = {};
		} else {
			var groups = { groupids: [ groupid ]};
		}
		
		var query = {
						jsonrpc: "2.0",
						method: "trigger.get",
						params: {
							monitored: true,
							expandDescription: true,
							min_severity: me.minseverity,
							expandData: true,
							output: ['description'],
							filter: {
								value: 1,
								value_flags: 0
							}
						},
						id: 1
				};
		//console.info("The query will be:");
		//console.log(groups);
		//console.log(query);
	 	if ( (HostsIds.length>0) && (me.ShowGroup==true) && (me.AlwaysShowAllHostGroups == false ) ){
			query.params = me.objMerge( query.params, {hostids: HostsIds});
		}
		else{   
			query.params = me.objMerge(query.params, groups)
		}	


		me.apiQuery(query, true, function(out){
			var x_max = 0;
			var y_max = 0;
			var x_min = 180;
			var y_min = 180;
			for (i = 0; i < out.result.length; i++) {
				(function(i) {
					// Selecting the coordinates 
					var hostQuery = {
							jsonrpc: "2.0",
							method: "host.get",
							params: {
								hostids: out.result[i].hostid,
								selectInventory:["location_lat","location_lon"]
							},
							id: i
					};
					//console.info("Doing problems():host.get");
					//console.log(hostQuery);
					me.apiQuery(hostQuery, true, function(data){
						if (data.result[0].inventory.location_lat == 0 || data.result[0].inventory.location_lon == 0) {
							var x = me.def_lat;
							var y = me.def_lon;
						} else {
							var x = data.result[0].inventory.location_lat;
							var y = data.result[0].inventory.location_lon;
						}
						if (x > x_max) x_max = x;
						if (x < x_min) x_min = x;
						if (y > y_max) y_max = y;
						if (y < y_min) y_min = y;
						//<--san 09.08.2013
						var ScriptsQuery= {
								jsonrpc: "2.0",
								method: "script.get",
								params: {
								     hostids: out.result[i].hostid,
								     output: "extend"
								},
								id: 1
						}; 
						// большой изврат тк просто переменная объявленная здесь на проходит через apiQuery
						san_links='';
						me.apiQuery(ScriptsQuery, true, function(data1){
							san_links='';
							for (k = 0; k < data1.result.length; k++){
							  san_links=san_links+' '+me.genLinks_script(out.result[i].hostid,data1.result[k].scriptid,data1.result[k].name);
							}
						me.ProblemArray.add(
    							new ymaps.Placemark([ x, y ],{
					    			balloonContent : out.result[i].hostname
											+ '<br>'
											+ out.result[i].description
											+'<br>'
											+me.genLinks(out.result[i].hostid)
											+'<br>Скрипты<br>'
											+san_links,
										iconContent : out.result[i].description,
										clusterCaption: out.result[i].hostname
								}, {
								preset : 'twirl#redStretchyIcon'
							}), i);
				    		},'Cannot load Scripts by host');
    						if (me.PrioProblem === 'true' && x_max != 0) {
    							me.Map.setBounds([ [ x_min, y_min ], [ x_max, y_max ] ], {
								duration : 1000,
								checkZoomRange : true
							});
						}

					}, 'Cannot load hosts');
					// Ajax is done
				})(i);
			}
			me.Map.geoObjects.add(me.ProblemArray);
			
		}, 'Cannot load triggers');
	},


    //      
        FillLinks: function(MyHost){
             var me=this;       
             var myout=''+MyHost.inventory.notes;
             var x = MyHost.inventory.location_lat;
             var y = MyHost.inventory.location_lon;     
             if ( myout.length>0 &&  myout!='undefined' ) { // есть линки
                   var myObject=jQuery.parseJSON(myout);
                   var arr1=jQuery.parseJSON(myObject.neighbour_peaks);  
                   var arr_strokeColor=jQuery.parseJSON(myObject.neighbour_peaks_strokeColor);
                   var arr_strokeWidth=jQuery.parseJSON(myObject.neighbour_peaks_strokeWidth);
                   var arr_strokeStyle=jQuery.parseJSON(myObject.neighbour_peaks_strokeStyle);
                   var arr_hintContent=jQuery.parseJSON(myObject.neighbour_peaks_hintContent);
                   var arr_linkCoordinates=jQuery.parseJSON(myObject.neighbour_peaks_linkCoordinates);  
                     // ну это просто изврат    
                    if (jQuery.isArray(arr_strokeColor)==false){arr_strokeColor=[];  for (var i=0; i<arr1.length;i++){ arr_strokeColor[i]='#0000FF';} }
                    if (jQuery.isArray(arr_strokeWidth)== false){arr_strokeWidth=[]; for (var i=0; i<arr1.length;i++){arr_strokeWidth[i]=2;} }          
                    if (jQuery.isArray(arr_strokeStyle)== false){arr_strokeStyle=[]; for (var i=0; i<arr1.length;i++){arr_strokeStyle[i]='';} }
                    if (jQuery.isArray(arr_hintContent)== false){arr_hintContent=[]; for (var i=0; i<arr1.length;i++){arr_hintContent[i]='';} } 
                    if (jQuery.isArray(arr_linkCoordinates)== false){arr_linkCoordinates=[]; for (var i=0; i<arr1.length;i++){arr_linkCoordinates[i]=[[x,y],[x,y]]; } } 

              	  //console.log('id='+MyHost.hostid+ 'Peaks='+arr1);

                   var add_toLinks=false;
                  for (var k=0 ;k<arr1.length;k++){
                    // arr2 сортированный двухэлементный массив  
                    // Link'и будут начинаться с наименьше вершины (hostid) и заканчиваться наибольшей  
                    var arr2=[arr1[k],arr1[k]];
                    var  tmp_coord=[];   
                    /*
                     координаты сначала для линка будут точка
                     лучше так, чем потерять связь между точками
                     при записи     
                    */

                    if (0+MyHost.hostid<0+arr1[k]){arr2[0]=MyHost.hostid;tmp_coord=[[x,y],[x,y]];}
                    else{arr2[1]=MyHost.hostid;tmp_coord=[[x,y],[x,y]];arr_linkCoordinates[k].reverse();}
                    //console.log('arr2='+arr2);
                    //пробежимся по LinksArray и заполним  некоторые элементы
                    add_toLinks=true;    
                    for (var t=0 ;t<me.LinksArray.getLength();t++){
                        var  Link= me.LinksArray.get(t);
        	        var  tmp_edge0= Link.properties.get('edge0');
                        var  tmp_edge1= Link.properties.get('edge1');
                        if (tmp_edge0==MyHost.hostid){
                            Link.geometry.set(0,[x,y]);
                            Link.options.set('visible',true); // а вот и вторая вершина линка появилась
                            if (tmp_edge1==arr2[1]){
                             // такой лин уже есть в  LinksArray
                                add_toLinks=false;
                            }
                       }
                        if (tmp_edge1==MyHost.hostid){
                             Link.geometry.set(Link.geometry.getLength()-1,[x,y]);
                             Link.options.set('visible',true); // а вот и вторая вершина линка появилась        
                            if (tmp_edge0==arr2[0]){
                             // такой лин уже есть в  LinksArray
                                 add_toLinks=false;
                             }
                        }

                  }//for            
                 //  А теперь добавим новую точку
                 if ( (add_toLinks==true) && !(MyHost.notAddNewLink=== true)){    
                      var myLink=new ymaps.GeoObject({
                                 geometry: {
                                            type: "LineString",
                                            coordinates: arr_linkCoordinates[k]
                                           },
                               properties:{
                                            hintContent: arr_hintContent[k],

                                            balloonContent: '',
                                            edge0 :  arr2[0],
                                            edge1 : arr2[1],
                                            my_hintContent: arr_hintContent[k],
                                           }
                                      }, {
                                            draggable: false,
                                            strokeColor: arr_strokeColor[k],
                                            strokeWidth: arr_strokeWidth[k],
                                            strokeStyle: arr_strokeStyle[k],
                                            visible: true      //ЗДЕСЬ сразу будет true
                             });
                 //console.log('edge0='+arr2[0]+' edge1='+arr2[1]+' Coords='+arr_linkCoordinates[k]);   
                 //  myLink.events.add('dblclick',function(event){ me.SetOptionsLink(myLink,event);});

                   me.LinksArray.add( myLink);
                 }//if      
             }//for
           }//if        
    },  




  /*
	FillBallon  временно не будем использовать
	проблема возникает , когда хосты попадают в кластер и двойной клик
	на который настроен  FillBallon не срабатывает
	надо будет разобраться с оптимальным алгоритмом заполнения balloonContent для хоста			
  */

   FillBallon: function(Placemark){
	 var me=this;	
	 var hostid=Placemark.properties.get('hostid');   
         var fill_ballon=Placemark.properties.get('fill_ballon');
         if  (fill_ballon== true) {return;}
	 Placemark.properties.set('fill_ballon',true);	
  	 var balloonContent=Placemark.properties.get('balloonContent');
         balloonContent=balloonContent+'<br>'+me.genLinks(hostid);
	 Placemark.properties.set('balloonContent',balloonContent);
         var ScriptsQuery= {
                           jsonrpc: "2.0",
                           method: "script.getscriptsbyhosts",
                           params: [hostid],
                           id: 1000008
                           }; 
//		console.log(ScriptsQuery);
	      me.apiQuery(ScriptsQuery, true, function(data1){
		console.log ('Fillbaloon');	
                console.log(data1);
                   var san_links='';
                   var myarr=data1.result[hostid];
                   if (jQuery.isArray(myarr)){  
                           for (var k = 0; k < myarr.length; k++){      
                                san_links=san_links+' '+me.genLinks_script(hostid,myarr[k].scriptid,myarr[k].name);
                           }
                   }
                   if(san_links.length>0){
                          var bal1=Placemark.properties.get('balloonContent');
                          Placemark.properties.set('balloonContent',bal1+'<br><br>Скрипты<br>'+san_links);
                        // console.log('i='+i+'  ==>'+Host.properties.get('balloonContent'));    
                   }    

             },'Cannot load Scripts by host');
		//	console.log(''+hostid);
   },


	// Добавляет на карту вершины линков , которые не попали в группу в функции ChangeGroup
	// Это почти то-же , что и ChangeGroup, только дополнительные линки не рисуются	
	AddExtendEdges: function(ArrayIds){
	   var me=this;	
	   var addHosts = []; 
 	   for (var t=0 ;t<me.LinksArray.getLength();t++){
              var  Link= me.LinksArray.get(t);
              var  tmp_edge0= Link.properties.get('edge0');
              var  tmp_edge1= Link.properties.get('edge1');
		//console.log('Link'+t+' :'+ tmp_edge0+' - '+ tmp_edge1);
	      if (addHosts.indexOf(tmp_edge0)<0) {addHosts.push(tmp_edge0)}
	      if (addHosts.indexOf(tmp_edge1)<0) {addHosts.push(tmp_edge1)}
	   }
		 //console.log('0 AddExtendEdges :'+addHosts);
		 //console.log(ArrayIds);
	   for (var t=0 ;t<ArrayIds.length;t++){
		var index=addHosts.indexOf(ArrayIds[t]);
		if (index>=0){addHosts.splice(index,1);}	
	   } 
		//console.log('1 AddExtendEdges :'+addHosts);
		
		

	    var query = {
                                jsonrpc: "2.0",
                                method: "host.get",
                                params: {
                                        output:["host","name","hostid"],
                                        selectInventory:["location_lat","location_lon","notes"],
					hostids: addHosts
                                },
                                id: 100
                        };
		//console.log(query);
             me.apiQuery(query, true, function(out) { 
			var bounds=me.Map.getBounds();
                        var x_max = bounds[1][0];
                        var y_max = bounds[1][1];
                        var x_min = bounds[0][0];
                        var y_min = bounds[0][1];
                        var hostIds=[];
			// Добываем  картинку из presetStorage
                        var imgshadow=ymaps.option.presetStorage.get('twirl#nightClusterIcons');
                         //console.log(url_imgshadow.clusterIcons[0].href);

                        for ( var i = 0; i < out.result.length; i++) {
                                if (out.result[i].inventory.location_lat == 0 || out.result[i].inventory.location_lon == 0) {
                                        x = me.def_lat;
                                        y = me.def_lon;
                                        iconPreset = 'twirl#darkorangeDotIcon';
                                } else {
                                        x = out.result[i].inventory.location_lat;
                                        y = out.result[i].inventory.location_lon;
                                        iconPreset = 'twirl#greenIcon';
                                }
                                if (x > x_max) x_max = x;
                                if (x < x_min) x_min = x;
                                if (y > y_max) y_max = y;
                                if (y < y_min) y_min = y;

				// не добавлятть новый линк в FillLinks
				out.result[i].notAddNewLink= true;
				me.FillLinks(out.result[i]);

                                            var aaaa = new ymaps.Placemark(
                                                [ x, y ], 
                                                {
                                                        hintContent : out.result[i].name+'<br>('+out.result[i].hostid+')' ,
                                                        hostid : out.result[i].hostid,
                                                        fill_ballon: false,
                                                        clusterCaption: out.result[i].name,
                                                        balloonContent : out.result[i].host+'<br>('+out.result[i].hostid+')'

                                                 },
                                                {
						       iconShadow: true,
					               iconShadowImageHref: imgshadow.clusterIcons[0].href,
						       iconShadowImageSize: [20, 20],
						       iconShadowImageOffset: [-10, -10],
						       iconShadowOffset: [0, 0],
                                                        draggable : false,
                                                        preset : iconPreset
                                                }

                                           );
                                                // var myout=out.result[i].inventory.notes
                                                var myObject=jQuery.parseJSON(out.result[i].inventory.notes);   
                                                //console.log(myObject);
                                                if (myObject!=null){
                                                        var ImagesArray=jQuery.parseJSON(myObject.ImagesArray);
                                                        if (jQuery.isArray(ImagesArray)==true){
                                                         //console.log(ImagesArray[0]);
                                                          aaaa.properties.set('imageid',ImagesArray[0].imageid);
                                                          aaaa.options.set({
                                                                iconImageHref:  'imgstore.php?iconid=' +ImagesArray[0].imageid,
                                                                iconImageSize:  ImagesArray[0].iconImageSize,
                                                                iconImageOffset:ImagesArray[0].iconImageOffset
                                                         });
                                                        }//if
	                                             }

                         me.Hosts.push(aaaa);
 			 me.HostArray.add(aaaa);
                         hostIds.push(out.result[i].hostid);
                        }//for
			me.problems();
	                me.Map.setBounds([ [ x_min, y_min ], [ x_max, y_max ] ], {
                                duration : 1000,
                                checkZoomRange : true
                        });


	       }, 'Cannot load hosts');
             return ArrayIds.concat(addHosts);

	}, 
	// san -->

	/**
	 * Redisplays the hosts, which are belonged to the certain group
	 */
	ChangeGroup: function(){
		//console.info('ZabbixYaMapRW.ChangeGroup() was called');
		var me = this;
                var sel = document.getElementById("selectgroup");
                var groupid = sel.options[sel.selectedIndex].value;

		if (me.ShowGroup==false){
                        me.Map.geoObjects.remove(me.HostArray);
                        me.Map.geoObjects.remove(me.LinksArray);
			me.problems();
			return;
		}
		else{
     			if (me.CurrentSelectGroup == groupid) {
                                me.Map.geoObjects.add(me.HostArray);
                                me.Map.geoObjects.add(me.LinksArray);
	                        me.problems();
        	                return;
			}
		}	

		if ( me.AlwaysShowAllHostGroups == true ) {groupid=0;}

                //san
                me.CurrentSelectGroup=groupid;          
		me.HostArray.removeAll();
		me.Map.geoObjects.remove(me.HostArray);
                me.Hosts.length=0;
		me.Map.geoObjects.remove(me.LinksArray);
		me.LinksArray.removeAll();


		var query = {
				jsonrpc: "2.0",
				method: "host.get",
				params: {
					output:["host","name","hostid"],
					selectInventory:["location_lat","location_lon","notes"]
				},
				id: 1
			};

		if(groupid == 0){
			var groups = {};
		} else {
			var groups = { groupids: [ groupid ]};
		}
		query.params = me.objMerge(query.params, groups);

		//console.info('Preparing to do the query');
		//console.log(query);
		
		me.apiQuery(query, true, function(out) {
			var x_max = 0;
			var y_max = 0;
			var x_min = 180;
			var y_min = 180;
			var hostIds=[];
            //console.info('Got the result');
            //console.log(out);
            //console.log(me);
			for ( var i = 0; i < out.result.length; i++) {
				//console.info("'this' in processing results");
				//console.log(me);
				/* If there is no Lattitude and Longtitude came from Zabbix */
				if (out.result[i].inventory.location_lat == 0 || out.result[i].inventory.location_lon == 0) {
					x = me.def_lat;
					y = me.def_lon;
					iconPreset = 'twirl#darkorangeDotIcon';
				} else {
					x = out.result[i].inventory.location_lat;
					y = out.result[i].inventory.location_lon;
					iconPreset = 'twirl#greenIcon';
					//san
					me.FillLinks(out.result[i]);
				}
				if (x > x_max) x_max = x;
				if (x < x_min) x_min = x;
				if (y > y_max) y_max = y;
				if (y < y_min) y_min = y;
				//console.info('Defining new host');
                                             //console.log(''+out.result[i].name+'   '+out.result[i].hostid);
                                               var aaaa = new ymaps.Placemark(
                                                [ x, y ], 
                                                {
                                                        hintContent : out.result[i].name+'<br>('+out.result[i].hostid+')' ,
                                                        hostid : out.result[i].hostid,
							fill_ballon: false,
							clusterCaption: out.result[i].name,
                                                        balloonContent : out.result[i].host+'<br>('+out.result[i].hostid+')'
                                                                                        
                                                 },
                                                {
                                                        draggable : false,
                                                        preset : iconPreset,
                                                        /*
                                                        iconImageHref: '/imgstore.php?iconid=100100000000558',
                                                        iconImageSize: [30, 30],
                                                        iconImageOffset: [-3, -30]
                                                        */
                                                }

                                           );
               			                 //<--  san
                                
                                		// var myout=out.result[i].inventory.notes
		                                var myObject=jQuery.parseJSON(out.result[i].inventory.notes);   
		                                //console.log(myObject);
		         		        if (myObject!=null){
				                      	var ImagesArray=jQuery.parseJSON(myObject.ImagesArray);
                                			if (jQuery.isArray(ImagesArray)==true){
		                                         //console.log(ImagesArray[0]);
                		                          aaaa.properties.set('imageid',ImagesArray[0].imageid);
                                		          aaaa.options.set({
                                                	        iconImageHref:  'imgstore.php?iconid=' +ImagesArray[0].imageid,
		                                                iconImageSize:  ImagesArray[0].iconImageSize,
                		                                iconImageOffset:ImagesArray[0].iconImageOffset
                                		         });
		                                 	}//if
                		                }
                                		// san ->

/*
					 (function(aaaa){
						 aaaa.events.add('balloonopen', function () { me.FillBallon(aaaa);});		
					})(aaaa);
					
	
					(function(aaaa){ 
						me.FillBallon(aaaa);           
        	                         })(aaaa);
*/
			 me.Hosts.push(aaaa);	
	               	 hostIds.push(out.result[i].hostid);
			}//for

			// Заполним 'граничные точки' и добавмим их id  в hostIds
			hostIds=me.AddExtendEdges(hostIds);
			me.AddLinksScript(hostIds);
			me.HostArray.add(me.Hosts);
			//console.info('ALl the hosts');
			//console.log(me.HostArray);
			me.Map.geoObjects.add(me.HostArray);
			//san
			me.Map.geoObjects.add(me.LinksArray); 	
			// Zoom the map
				me.Map.setBounds([ [ x_min, y_min ], [ x_max, y_max ] ], {
					duration : 1000,
					checkZoomRange : true
				});
		}, 'Cannot load hosts');
         

	}
});

</script>
