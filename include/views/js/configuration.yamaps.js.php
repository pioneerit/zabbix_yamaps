   <style type="text/css">
        #menu {
            position: absolute;
            background: #FFFFCC;
            border: 1px solid #006600;
            border-radius: 12px;
            padding-bottom: 10px;
            z-index: 2
        }
        #menu ul {
            list-style-type: none;
            padding: 20px;
            margin: 0;
        }
        input {
            width: 10em;
        }
   </style>

<script type="text/javascript" src="/js/jscolor/jscolor.js"></script>

<script type="text/javascript">
var ZabbixYaMapRW = Class.create(ZabbixYaMap, {
	/* Add variables */
	Hosts      : [],        // Placemarks
	ChangeHost : [],        // Contains the changed hosts (e.g. dragged hosts)
	HostArray  : undefined, // Cluster of geo objects
	saved      : false,     // were the hosts saved?
	//
	SaveButton : undefined,

	my_count  : 0,
	tmp_link_coord: [],
	tmp_placemarks: [],
	LinksArray :  undefined,
	LinksChange :	[],     // Contains the changed edges of links 
	/* Add new methods */
	/**
	 * Initialization of additional controls
	 */
	init: function() {
		//console.info('ZabbixYaMapRW.init() was called');
		var me = this;
		me.HostArray = new ymaps.Clusterer({ maxZoom : 9});
		//san
		me.LinksArray = new ymaps.GeoObjectArray({},{mylink:1});
		me.SetSelect(document.getElementById("selectgroup"), "<?php echo _('All'); ?>", "<?php echo _('All'); ?>");
		
		me.SaveButton = new ymaps.control.Button({
			data : {
				content : '<?php echo _('Save'); ?>',
				title : '<?php echo _('Press to save the positions'); ?>'
			}
		}, {
			position : {
				top : 5,
				right : 200
			},
			selectOnClick :  true
		});
		me.SaveButton.disable();
		me.Map.controls.add(me.SaveButton);
		me.saved = false;


		//<-- san 10.08.2013
               me.AddLinkButton = new ymaps.control.Button({
                        data : {
                                content : "+",
                                title : '<?php echo _('Нажмите для добавления линка м/у двумя хостами. Сделайте двойной клик на первом хосте и двойной клик на втором хосте'); ?>'
                        }
                }, {
                        position : {
                                top : 5,
                                right : 350
                        },
                        selectOnClick : true
                });
                me.AddLinkButton.enable();
		me.AddLinkButton.state.set('selected',false);
  	        me.AddLinkButton.events.add('click',function(){
 			//console.log('Button "+" is clicked '+ me.AddLinkButton.isSelected()  ); 
			me.my_count=0;
			me.DelLinkButton.state.set('selected',false);
			
		});
                me.Map.controls.add(me.AddLinkButton);


               me.DelLinkButton = new ymaps.control.Button({
                        data : {
                                content : "-",
                                title : '<?php echo _('Press to delete link beetwin 2 hosts'); ?>'
                        }
                }, {
                        position : {
                                top : 5,
                                right : 330
                        },
                        selectOnClick : true
                });
                me.DelLinkButton.enable();
                me.DelLinkButton.state.set('selected',false);
                me.DelLinkButton.events.add('click',function(){
                        //console.log('Button "-" is clicked '+ me.AddLinkButton.isSelected()  );
			 me.AddLinkButton.state.set('selected',false);
                        me.my_count=0;

                });
                me.Map.controls.add(me.DelLinkButton);


		//san -->
		me.ChangeGroup();
		// Set up onChange reaction
		jQuery('#selectgroup').change(function() {
			me.ChangeGroup();
		});
	},

	/**
	 * Saves the changed hosts
	 */
	save_change: function() {
		var me = this;
		for (var i = 0; i < this.ChangeHost.length; i++) {
			
			var query = {
					jsonrpc:"2.0",
					method:"host.update",
					params: {
						hostid: me.ChangeHost[i].hid,
						inventory: {
							location_lat: me.ChangeHost[i].point[0].toFixed(12),
							location_lon: me.ChangeHost[i].point[1].toFixed(12)
						}
					},
					id: i
				};
			me.apiQuery(query, true, function(){
				me.ChangeHost.length = 0;
				me.SaveButton.disable();
				me.saved = false;
				me.SaveButton.events.remove('click', function() {
			    	me.save_change();
			    });
			}, 'Cannot save the objects');
	     	} //for
         //<--san 
	// Запишем измененные линки (точнее вершины)
	for (var i = 0; i < me.LinksChange.length; i++) {
	// for (var i = 0; i < me.Hosts.length; i++) {	
		var my_hostid=me.LinksChange[i];
		//var my_hostid=me.Hosts[i].properties.get('hostid');
		var neighbour_peaks=[];
		var neighbour_peaks_strokeColor=[];
		var neighbour_peaks_strokeWidth=[];
		var neighbour_peaks_hint=[];

			neighbour_peaks.length=0;	
                        // в цикле формируем массив связянных с данной вершин
			for (var j=0 ; j < me.LinksArray.getLength();j++){
				var Link=me.LinksArray.get(j);
				var  tmp_edge0= Link.properties.get('edge0');
				var  tmp_edge1= Link.properties.get('edge1');
				var  tmp_strokeColor=Link.options.get('strokeColor');
				var  tmp_strokeWidth=Link.options.get('strokeWidth');
				var  tmp_hintContent=Link.properties.get('my_hintContent');

				if (my_hostid ==tmp_edge0) {
					neighbour_peaks.push(tmp_edge1);
					neighbour_peaks_strokeColor.push(tmp_strokeColor);
					neighbour_peaks_strokeWidth.push(tmp_strokeWidth); 
					neighbour_peaks_hint.push(tmp_hintContent);
					}
				if (my_hostid ==tmp_edge1) {
					neighbour_peaks.push(tmp_edge0);
					neighbour_peaks_strokeColor.push(tmp_strokeColor);
					neighbour_peaks_strokeWidth.push(tmp_strokeWidth);
 				        neighbour_peaks_hint.push(tmp_hintContent);
					}
			}
			// Будем записывать через объект. Возможно придется раширять некоторые параметры записей
			var MyObject= new Object ({
					neighbour_peaks: neighbour_peaks,
					neighbour_peaks_strokeColor: neighbour_peaks_strokeColor,
					neighbour_peaks_strokeWidth: neighbour_peaks_strokeWidth,
					neighbour_peaks_hintContent: neighbour_peaks_hint 
				}); 
			//console.log('id='+my_hostid+'  array='+neighbour_peaks);
                        var json_object=JSON.stringify(MyObject);
			//console.log(json_object);
                        var query1 = {
                                        jsonrpc:"2.0",
                                        method:"host.update",
                                        params: {
                                                hostid: me.LinksChange[i],
                                                inventory: {
                                                        notes: json_object
                                                }
                                        },
                                        id: i
                                     };

                            
                        me.apiQuery(query1, true, function(){
                                me.SaveButton.disable();
                                me.saved = false;
                                me.SaveButton.events.remove('click', function() {
                                me.save_change();
                            });
                        }, 'Cannot save the objects');
			
		}	
		me.LinksChange.length=0;
 	//san -->
	},

	/**
	 * Drags the hosts
	 */
	draghost: function(id, newpoint) {
		// TODO: FIX, how many hosts there will be if we'll drag it all the time?? We need only the last value!
		var me = this;
		me.ChangeHost.push(new Object({
			hid: id,
			point: newpoint
		}));

                //san   13.08.2013
                me.ChangeLinks(id,newpoint);

		if (me.saved == false) {
			me.saved = true;
			me.SaveButton.enable();
			me.SaveButton.events.add('click', function() {
				me.save_change();
	        });
	    	}
	},
	//<-- san  	11.08.2013
	// Эта функция просто запоминает изменения относительно вершин линков в массиве LinksChange
	// для последующей записи этих измений в базу
	// вызывается она всего из функций : AddNewLink   DelLink  :
	MakeLinksChange: function(){
             var me=this;
	     var flag_id0=false;
	     var flag_id1=false;	 	
             var id0=me.tmp_placemarks[0].properties.get('hostid');
             var id1=me.tmp_placemarks[1].properties.get('hostid');
		// делаем симметричные действия для двух вершин линка
             for (var i=0 ;i<me.LinksChange.length;i++){
                 if (me.LinksChange[i]== id0) {flag_id0=true;}
		 if (me.LinksChange[i]== id1) {flag_id1=true;}

 	     }	
	     if (flag_id0==false){me.LinksChange.push(id0);}
	     if (flag_id1==false){me.LinksChange.push(id1);}	
	},	
	ChangeLinks: function(id,newpoint_coords){
		var me=this;
		for(var i=0; i<me.LinksArray.getLength(); i++) {
			var  Link=me.LinksArray.get(i);
		 	var  tmp_edge0= Link.properties.get('edge0');
	 		var  tmp_edge1= Link.properties.get('edge1');
			var  tmp_coords= Link.geometry.getCoordinates();
			//console.log('==>'+i+'  edge0='+ tmp_edge0+' edge1='+tmp_edge1+' id='+id+ ' ,tmpcoords='+tmp_coords+'  ,newpoint_coords='+newpoint_coords);	
			if  (tmp_edge0==id){
				tmp_coords[0]=newpoint_coords;
				Link.geometry.setCoordinates(tmp_coords);
			}	 
                        if  (tmp_edge1==id){
                                tmp_coords[1]=newpoint_coords;
				Link.geometry.setCoordinates(tmp_coords);
                        }
		}
	},	

	 SetOptionsLink: function (Link,event){
		var me=this;
            	var coords = Link.geometry.getCoordinates();
	        var id0=Link.properties.get('edge0');
        	var id1=Link.properties.get('edge1');

		//console.log(event.get('position'));
		//console.log('Link dblclik '+ Link.properties.get('edge0')+' --- '+Link.properties.get('edge1'));	
                // смотри http://api.yandex.ru/maps/jsbox/geoobject_contextmenu
                // HTML-содержимое контекстного меню.
		    // Если меню метки уже отображено, то убираем его.
	        if (jQuery('#menu').css('display') == 'block') { jQuery('#menu').remove();}
		else{
            	var menuContent =
                	'<div id="menu">\
			<div align="center"> Ввод параметров ломаной</div>\
	                    <ul id="menu_list">\
        	                <li>Толщина линии: <br /> <input type="text" name="hline" /></li>\
                	        <li>Цвет линии: <br /> <input class="color" name="colorpline" /></li>\
				<li>Подсказка (hint): <br /> <textarea rows="5" cols="20" name="hint" wrap="soft"></textarea>\
        	            </ul>\
                	<div align="center"><input type="submit" value="Сохранить" /></div>\
	                </div>'; 
	       // Размещаем контекстное меню на странице
                  jQuery('body').append(menuContent);
	       // Задаем позицию меню.
               jQuery('#menu').css({
                   left: event.get('position')[0],
                   top: event.get('position')[1]
               });
		// Заполняем значения формы текущими значениями Link
		jQuery('input[name="hline"]').val(Link.options.get('strokeWidth'));
		jQuery('input[name="colorpline"]').val(Link.options.get('strokeColor'));
		if (Link.properties.get('my_hintContent')!=''){jQuery('textarea[name="hint"]').val(Link.properties.get('my_hintContent'));}	

		//как хорошо, что есть www.jscolor.com
		jscolor.bind();

	       jQuery('#menu input[type="submit"]').click(function () {
			Link.options.set({
			strokeWidth:jQuery('input[name="hline"]').val(), 
			strokeColor:jQuery('input[name="colorpline"]').val()
			});

			Link.properties.set('my_hintContent',jQuery.trim( jQuery('textarea[name="hint"]').val() ) );
			if ( Link.properties.get('my_hintContent')!='' ) {
				 Link.properties.set('hintContent', jQuery.trim(jQuery('textarea[name="hint"]').val())  ) 
			}
		        var flag_id0=false;
		        var flag_id1=false;
	                // делаем симметричные действия для двух вершин линка
        		for (var i=0 ;i<me.LinksChange.length;i++){
	                 if (me.LinksChange[i]== id0) {flag_id0=true;}
        	         if (me.LinksChange[i]== id1) {flag_id1=true;}

	               }  
          	       if (flag_id0==false){me.LinksChange.push(id0);}
	               if (flag_id1==false){me.LinksChange.push(id1);}   

		       if (me.saved == false) {
                        me.saved = true;
                        me.SaveButton.enable();
                        me.SaveButton.events.add('click', function() {
                                me.save_change();
                        });
                       }

		      // Удаляем контекстное меню.
        	        jQuery('#menu').remove();
	       });	
	       } //else	

	},

	 AddNewLink: function(){
	     var me=this;	
	     var coords=[];
		coords[0]=  me.tmp_placemarks[0].geometry.getCoordinates();
		coords[1]=  me.tmp_placemarks[1].geometry.getCoordinates();
	     var my_hint='Сделайте dblclick на линке для редактирования его свойств';
		/*
		my_hint='Link: '+ me.tmp_placemarks[0].properties.get('hintContent')+ ' - ' 
				+ me.tmp_placemarks[1].properties.get('hintContent')+ ' <br> '
				+ 'Сделайте dblclick на линке для редактирования его свойств'
		*/

               // console.log( me.tmp_placemarks[0].properties.get('hostid'));
	       // console.log( me.tmp_placemarks[1].properties.get('hostid'));

             var myLink=new ymaps.GeoObject({
             // Описываем геометрию геообъекта.
                   geometry: {
                   // Тип геометрии - "Ломаная линия".
                            type: "LineString",
                            // Указываем координаты вершин ломаной.
                             coordinates:  coords
                             },
                   // Описываем свойства геообъекта.
                    properties:{
                        // Содержимое хинта.
                        hintContent: my_hint,
			// Содержимое балуна.
                        balloonContent: '',
                        // неохота заморачиватся с regexp 
			my_hintContent:'',
			edge0 :  me.tmp_placemarks[0].properties.get('hostid'),
                        edge1 :  me.tmp_placemarks[1].properties.get('hostid')
			}	
                       
                    }, {
                    // Задаем опции геообъекта.
		     // Включаем возможность перетаскивания ломаной.
                       draggable: false,
                    // Цвет линии.
                       strokeColor: "#0DFF00",
                    // Ширина линии.
                       strokeWidth: 2
                   });

               myLink.events.add('dblclick',function(event){ me.SetOptionsLink(myLink,event);});
	   //  console.log(myLink.properties.get('edge0')+' Length= '+ me.LinksArray.getLength());	
   	     me.Map.geoObjects.remove(me.LinksArray);
	     me.LinksArray.add(myLink);            
 	     me.Map.geoObjects.add(me.LinksArray);
	  // запоминаем эти изменения для последующей записи в базу через JSON
	      me.MakeLinksChange();
               if (me.saved == false) {
                        me.saved = true;
                        me.SaveButton.enable();
                        me.SaveButton.events.add('click', function() {
                                me.save_change();
                	});
		}

	},

	DelLink: function(){
             var me=this;
	     var id0=me.tmp_placemarks[0].properties.get('hostid');
	     var id1=me.tmp_placemarks[1].properties.get('hostid');
		 me.Map.geoObjects.remove(me.LinksArray);
                for(var i=0; i<me.LinksArray.getLength(); i++) {
			var  Link= me.LinksArray.get(i);
                        var  tmp_edge0= Link.properties.get('edge0');
                        var  tmp_edge1= Link.properties.get('edge1');
                        //console.log('==>'+i+'  edge0='+ tmp_edge0+' edge1='+tmp_edge1+' id0='+id0+ 'id1='+id1);

                       if  ( ((tmp_edge0==id0) && (tmp_edge1==id1)) || ((tmp_edge0==id1) && (tmp_edge1==id0))  )  {
				//console.log('Условие сработало');
                                me.LinksArray.splice(i,1);
				//console.log('Удален идекс='+i);
                        }
                }
		 me.Map.geoObjects.add(me.LinksArray);
		// запоминаем эти изменения для последующей записи в базу через JSON
              	me.MakeLinksChange();
               if (me.saved == false) {
                        me.saved = true;
                        me.SaveButton.enable();
                        me.SaveButton.events.add('click', function() {
                                me.save_change();
                	});
		}

	},
        //  FillLinks Вызывается из ChangeGroup
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
		   var arr_hintContent=jQuery.parseJSON(myObject.neighbour_peaks_hintContent);
		     // ну это просто изврат	
		    if (jQuery.isArray(arr_strokeColor)==false){arr_strokeColor=[];  for (var i=0; i<arr1.length;i++){ arr_strokeColor[i]='#0000FF';} }
		    if (jQuery.isArray(arr_strokeWidth)== false){arr_strokeWidth=[]; for (var i=0; i<arr1.length;i++){arr_strokeWidth[i]=2;} }		
		    if (jQuery.isArray(arr_hintContent)== false){arr_hintContent=[]; for (var i=0; i<arr1.length;i++){arr_hintContent[i]='';} }	
/*
			console.log('-----------------------------------');
			console.log('id='+MyHost.hostid);
			console.log(myObject);	
			console.log(arr_strokeColor);
			console.log(arr_strokeWidth);
			console.log(arr_hintContent);
			console.log(arr1);
*/
	       	   var add_toLinks=false;      
	          for (var k=0 ;k<arr1.length;k++){
	            // arr2 сортированный двухэлементный массив  
	            var arr2=[arr1[k],arr1[k]];
	            var  tmp_coord=[];   
	            if (MyHost.hostid<arr1[k])
	            /*
	             координаты сначала для линка будут точка
	             лучше так, чем потерять связь между точками
	             при записи     
	            */
        	        {arr2[0]=MyHost.hostid;tmp_coord=[[x,y],[x,y]];}
	                else{arr2[1]=MyHost.hostid;tmp_coord=[[x,y],[x,y]];}
        	    //console.log(''+arr2);
	            //пробежимся по LinksArray и заполним  некоторые элементы
	            add_toLinks=true;    
	            for (var t=0 ;t<me.LinksArray.getLength();t++){
	                var  Link= me.LinksArray.get(t);
	                var  tmp_edge0= Link.properties.get('edge0');
	                var  tmp_edge1= Link.properties.get('edge1');
	                if (tmp_edge0==MyHost.hostid){
	                    Link.geometry.set(0,[x,y]);
	                    if (tmp_edge1==arr2[1]){
	                     // такой лин уже есть в  LinksArray
	                        add_toLinks=false;
        	            }
 	               }
                	if (tmp_edge1==MyHost.hostid){
	                     Link.geometry.set(1,[x,y]);
	                     if (tmp_edge1==arr2[1]){
	                     // такой лин уже есть в  LinksArray
	                         add_toLinks=false;
	                     }
        	        }
			
	          }//for            
	         //  А теперь добавим новую точку
	         if (add_toLinks==true){    
	              var myLink=new ymaps.GeoObject({
	                         geometry: {
	                                    type: "LineString",
	                                    coordinates:  tmp_coord
	                                   },
	                       properties:{
	                                    hintContent: arr_hintContent[k]+' <br> '+'<font color="red"> Сделайте dblclick на линке для редактирования его свойств</font>',

	                                    balloonContent: '',
	                                    edge0 :  arr2[0],
	                                    edge1 : arr2[1],
					    my_hintContent: arr_hintContent[k],
        	                           }
                	              }, {
	                                    draggable: false,
	                                    strokeColor: arr_strokeColor[k],
	                                    strokeWidth: arr_strokeWidth[k]
	                     });

                   myLink.events.add('dblclick',function(event){ me.SetOptionsLink(myLink,event);});

	           me.LinksArray.add( myLink);
	         }//if      
	     }//for
           }//if	
    },	
	//san ->	

	/**
	 * Redisplays the hosts, which are belonged to the certain group
	 */
	ChangeGroup: function(){
		//console.info('ZabbixYaMapRW.ChangeGroup() was called');
		var me = this;

		var sel = document.getElementById("selectgroup");
		var groupid = sel.options[sel.selectedIndex].value;

		me.HostArray.removeAll();
		me.Map.geoObjects.remove(me.HostArray);
		//<-- san 
		me.Map.geoObjects.remove(me.LinksArray);
		me.LinksArray.removeAll();
		// san -->	
		var query = {
				jsonrpc: "2.0",
				method: "host.get",
				params: {
					output:["host","name"],
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
            //console.info('Got the result');
            //console.log(out);
            //console.log(me);
			var array_of_peaks=[];
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
					iconPreset = 'twirl#blueIcon';

					//<--san
					me.FillLinks(out.result[i]);
					// san -->
				}
				if (x > x_max) x_max = x;
				if (x < x_min) x_min = x;
				if (y > y_max) y_max = y;
				if (y < y_min) y_min = y;
				//console.info('Defining new host');
				me.Hosts[i] = new ymaps.Placemark(
						[ x, y ], 
						{
							hintContent : ''+ out.result[i].name+' <br> '+out.result[i].hostid,
							hostid : out.result[i].hostid
						},
						{
							draggable : true,
							preset : iconPreset
						}
				);
				//console.log(me.Hosts[i]);
				(function(i) {
					me.Hosts[i].events.add('dragend', function() {
							me.draghost(
								me.Hosts[i].properties.get('hostid'),	
								me.Hosts[i].geometry.getCoordinates()
							);
						});
					me.Hosts[i].events.add('dblclick', function() {
						//window.alert('dblclick '+ me.Hosts[i].properties.get('hostid')+ ' Coord:'+ me.Hosts[i].geometry.getCoordinates()+ "    "+me.AddLinkButton.isSelected());
						if (me.AddLinkButton.isSelected()==true){
							//console.log(""+ me.my_count+"   "+ me.Hosts[i].options.get('preset'));
							if (me.my_count<=1){
								//me.tmp_link_coord[me.my_count]= me.Hosts[i].geometry.getCoordinates();
								me.tmp_placemarks[me.my_count]= me.Hosts[i];
								me.Hosts[i].options.set('preset' , 'twirl#redIcon');

								if (me.my_count==1){
								   if ( me.tmp_placemarks[0].properties.get('hostid')!=me.tmp_placemarks[1].properties.get('hostid')){	
									 me.AddNewLink();
								   }	
									// перекрашиваем  наши метки в обычный цвет
									 me.tmp_placemarks[0].options.set('preset' , 'twirl#blueIcon');
									 me.tmp_placemarks[1].options.set('preset' , 'twirl#blueIcon');
									// изврат
									 me.my_count=-1;
								}
								me.my_count=me.my_count+1;
							}

						}
						if (me.DelLinkButton.isSelected()==true){
                                                        //console.log(""+ me.my_count+"   "+ me.Hosts[i].options.get('preset'));
                                                        if (me.my_count<=1){
                                                                me.tmp_placemarks[me.my_count]= me.Hosts[i];
                                                                me.Hosts[i].options.set('preset' , 'twirl#redIcon');

                                                                if (me.my_count==1){
								   if ( me.tmp_placemarks[0].properties.get('hostid')!=me.tmp_placemarks[1].properties.get('hostid')){	
                                                                         me.DelLink();
								   }
                                                                        // перекрашиваем  наши метки в обычный цвет
                                                                         me.tmp_placemarks[0].options.set('preset' , 'twirl#blueIcon');
                                                                         me.tmp_placemarks[1].options.set('preset' , 'twirl#blueIcon');
                                                                        // изврат
                                                                         me.my_count=-1;
                                                                }
                                                                me.my_count=me.my_count+1;
                                                        }
							
						}

						});
				})(i);
				me.HostArray.add(me.Hosts[i]);
			}

			//console.info('ALl the hosts');
			//console.log(me.HostArray);
			me.Map.geoObjects.add(me.HostArray);
			me.Map.geoObjects.add(me.LinksArray);			
			// Zoom the map
			me.Map.setBounds([ [ x_min, y_min ], [ x_max, y_max ] ], {
				duration : 1000,
				checkZoomRange : true
			});
		}, 'Cannot load hosts');
	}
// The methods are over :(		
});

</script>
