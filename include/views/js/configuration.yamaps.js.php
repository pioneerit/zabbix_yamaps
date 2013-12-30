   <style type="text/css">
        #menu {
            position: absolute;
            background: #FFFFCC;
            border: 1px solid #006600;
            border-radius: 1px;
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

<script type="text/javascript" src="js/jscolor/jscolor.js"></script>

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
	ImagesArray: [],        // Contains the  Zabbix icons id
	TmpLabel:    [],        // Содержит две красные метки для обозначения точек - концов линка  		

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
                me.TmpLabel [0]= new ymaps.Placemark([me.deflat,me.deflon],{},{'preset' : 'twirl#redIcon'});       
		me.TmpLabel [1]= new ymaps.Placemark([me.deflat,me.deflon],{},{'preset' : 'twirl#redIcon'});

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
	   	
		me.FillImagesArray();
	},

	//<-- san
	FillImagesArray: function (){
		var me = this;
                var query = {
                       jsonrpc:"2.0",
                       method:"image.getobjects",
                       params: {
	                          imagetype: '1',
	                          output: 'imageid,name'
                                },
                       id: 1
                      };
                    me.apiQuery(query, true, function(imagedata){
        			  for ( var i = 0; i < imagedata.result.length; i++) {
					me.ImagesArray[i]=new Object ({
					    imageid: imagedata.result[i].imageid,
					    name: imagedata.result[i].name
					    });
				  }
			//	console.log(me.ImagesArray);
                        }, 'Cannot load images ids');


	},    

	//san-->
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
		//san
		me.save_change_another();
        
	},

	 //<--san 
        // Дополнительная запись в базу
	save_change_another: function() {
		var me = this;
	// Запишем измененные линки (точнее вершины)
	for (var i = 0; i < me.LinksChange.length; i++) {
		var my_hostid=me.LinksChange[i];
		var neighbour_peaks=[];
		var neighbour_peaks_strokeColor=[];
		var neighbour_peaks_strokeWidth=[];
		var neighbour_peaks_strokeStyle=[];
		var neighbour_peaks_hint=[];
		var neighbour_peaks_linkCoordinates=[];

			neighbour_peaks.length=0;	
                        // в цикле формируем массив связянных с данной вершин
			for (var j=0 ; j < me.LinksArray.getLength();j++){
				var  Link=me.LinksArray.get(j);
				var  tmp_edge0= Link.properties.get('edge0');
				var  tmp_edge1= Link.properties.get('edge1');
				var  tmp_strokeColor=Link.options.get('strokeColor');
				var  tmp_strokeStyle=Link.options.get('strokeStyle');
				var  tmp_strokeWidth=Link.options.get('strokeWidth');
				var  tmp_hintContent=Link.properties.get('my_hintContent');
				//  ДЕЛАЕМ КОПИЮ массива координат , т к  его возможно надо будет переворачивать 
				var  tmp_linkCoordinates=Link.geometry.getCoordinates().slice(0);

				if (my_hostid ==tmp_edge0) { // начальная точка
					neighbour_peaks.push(tmp_edge1);
					neighbour_peaks_strokeColor.push(tmp_strokeColor);
					neighbour_peaks_strokeStyle.push(tmp_strokeStyle);
					neighbour_peaks_strokeWidth.push(tmp_strokeWidth); 
					neighbour_peaks_hint.push(tmp_hintContent);
					neighbour_peaks_linkCoordinates.push(tmp_linkCoordinates);
					}
				if (my_hostid ==tmp_edge1) { // конечная точка
					neighbour_peaks.push(tmp_edge0);
					neighbour_peaks_strokeColor.push(tmp_strokeColor);
					neighbour_peaks_strokeStyle.push(tmp_strokeStyle);
					neighbour_peaks_strokeWidth.push(tmp_strokeWidth);
 				        neighbour_peaks_hint.push(tmp_hintContent);
					neighbour_peaks_linkCoordinates.push(tmp_linkCoordinates.reverse());
					}
			}
			
			// Будем записывать через объект. Возможно придется раширять некоторые параметры записей
			var MyObject= new Object ({
					neighbour_peaks: neighbour_peaks,
					neighbour_peaks_strokeColor: neighbour_peaks_strokeColor,
					neighbour_peaks_strokeWidth: neighbour_peaks_strokeWidth,
					neighbour_peaks_strokeStyle: neighbour_peaks_strokeStyle,
					neighbour_peaks_hintContent: neighbour_peaks_hint,
					neighbour_peaks_linkCoordinates: neighbour_peaks_linkCoordinates 
				}); 

			// Дополним запись сведениями о картинке Хоста
                        for (var k=0 ; k<me.Hosts.length;k++){
			     var myHost = me.Hosts[k];
			     //console.log('id='+ my_hostid +' hostid='+myHost.properties.get("hostid")+'  k='+k);
			     if (my_hostid==myHost.properties.get('hostid')){
				if (myHost.properties.get('imageid')==undefined){
					// Свойство не установлено -> нет картинки
					break;
				}
				//Есть картинка 

				var MyImage = new Object ({
					imageid: myHost.properties.get('imageid'),
					iconImageSize: myHost.options.get('iconImageSize'),
					iconImageOffset: myHost.options.get('iconImageOffset')

				    });
			// Будем записывать массив картинок (закладка на будущее развитие. Например картиник по типам проблем)	
				MyObject.ImagesArray = [MyImage];

				break;
				}
                        }
                        var json_object=JSON.stringify(MyObject);
			console.log(json_object);
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

                         
                        me.apiQuery(query1, true, function(){ }, 'Cannot save the objects');
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
                                tmp_coords[tmp_coords.length-1]=newpoint_coords;
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
			
			var OldCoordinates=Link.geometry.getCoordinates();

			// Запрещаем удаление вешины по двойному клику	
			Link.editor.options.set('dblClickHandler', function () {});
			// Заперщаем  перемещение 'крайних вершин'

			Link.editor.events.add(["beforevertexdrag"], function (event) {
				 var vertexModel = event.get('target').properties.get('model'),
         			  vertexIndex = vertexModel.getIndex();
		  	          if (vertexIndex == 0 || vertexIndex == vertexModel.getParent().getVertexModels().length - 1) {
				            event.preventDefault();
			          }
			 	});

			// Удаляем контекстное меню для 'крайних ' вершин
			// А вообще почему-то нельзя использовать Link.editor.options.set для MenuManager	
			Link.options.set({
				editorMenuManager: function (menuItems, vertex) {
					var myIndex=vertex.getIndex();
					if ((myIndex==0) || (myIndex==Link.geometry.getLength()-1) ){return [];}
				 	return menuItems;
				     }
			  		
			});
			 Link.editor.startEditing();

            	var menuContent =
                	'<div id="menu">\
			<div style="float:right"><img id="closebutton" src="images/general/error2.png" /> </div>\
			<div align="center"> Ввод параметров ломаной</div>\
	                    <ul id="menu_list">\
        	                <li>Толщина линии: <br /> <input type="text" name="hline" /></li>\
                	        <li>Цвет линии: <br /> <input class="color" name="colorpline" /></li>\
				<li>Стиль линии: <br /> <input type="text" name="strokestyle" \>\
				   <br>Если стиль имеет значение 1 5, это означает,\
				   <br>что надо нарисовать штрих\
				   <br> длиной одну ширину линии,\
				   <br>и потом 5 ширин пропустить\
				</li>\
				<br>\
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
		jQuery('input[name="strokestyle"]').val(Link.options.get('strokeStyle'));
		if (Link.properties.get('my_hintContent')!=''){jQuery('textarea[name="hint"]').val(Link.properties.get('my_hintContent'));}	

		//как хорошо, что есть www.jscolor.com
		jscolor.bind();

               // кнопка закрытия формы        
                jQuery('#closebutton').click(function() {
			Link.geometry.setCoordinates( OldCoordinates);
			Link.editor.stopEditing();
			jQuery('#menu').remove();
		});

	       jQuery('#menu input[type="submit"]').click(function () {
			Link.options.set({
			strokeWidth:jQuery('input[name="hline"]').val(), 
			strokeColor:jQuery('input[name="colorpline"]').val(),
			strokeStyle:jQuery('input[name="strokestyle"]').val()	
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
			// Запишем 'крайние'точки ломаной по старым координатам, если  чел их случайно передвинул
			var TmpCoordinates=Link.geometry.getCoordinates();
			TmpCoordinates[0]=OldCoordinates[0];
			TmpCoordinates[TmpCoordinates.length-1]=OldCoordinates[OldCoordinates.length-1];
			Link.geometry.setCoordinates(TmpCoordinates);
			 Link.editor.stopEditing();

			// Изменим логику записи в базу
			/*	
		       if (me.saved == false) {
                        me.saved = true;
                        me.SaveButton.enable();
                        me.SaveButton.events.add('click', function() {
                                me.save_change();
                        });
                        }
			*/
			 me.save_change_another();
                       

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
                       strokeColor: "#0000FF",
                    // Ширина линии.
                       strokeWidth: 2
                   });
		//Будем считаль edge0   хост с наименьшим id. edge1 -  хост с наибольшим id 	
		myLink.properties.set ('edge0',''+Math.min(0+me.tmp_placemarks[0].properties.get('hostid'),0+me.tmp_placemarks[1].properties.get('hostid') ) );
		myLink.properties.set ('edge1',''+Math.max(0+me.tmp_placemarks[0].properties.get('hostid'),0+me.tmp_placemarks[1].properties.get('hostid') ) );

               myLink.events.add('dblclick',function(event){ me.SetOptionsLink(myLink,event);});
	   //  console.log(myLink.properties.get('edge0')+' Length= '+ me.LinksArray.getLength());	
   	     me.Map.geoObjects.remove(me.LinksArray);
	     me.LinksArray.add(myLink);            
 	     me.Map.geoObjects.add(me.LinksArray);
	  // запоминаем эти изменения для последующей записи в базу через JSON
	      me.MakeLinksChange();
		/*
               if (me.saved == false) {
                        me.saved = true;
                        me.SaveButton.enable();
                        me.SaveButton.events.add('click', function() {
                                me.save_change();
                	});
		}
		*/
		me.save_change_another();	

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
		/*
               if (me.saved == false) {
                        me.saved = true;
                        me.SaveButton.enable();
                        me.SaveButton.events.add('click', function() {
                                me.save_change();
                	});
		}
		*/
		me.save_change_another();	

	},
        //  FillLinks Вызывается из ChangeGroup
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
/*
		console.log(MyHost);
		console.log(arr1 +' length'+arr1.length);	
		console.log(jQuery.parseJSON(myObject.neighbour_peaks_linkCoordinates));	
		console.log(jQuery.isArray(jQuery.parseJSON(myObject.neighbour_peaks_linkCoordinates)));
                console.log(arr_linkCoordinates);
*/
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
	            else{arr2[1]=MyHost.hostid;tmp_coord=[[x,y],[x,y]]; arr_linkCoordinates[k].reverse();}
        	    //console.log(''+arr2);
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
	         if (add_toLinks==true){    
	              var myLink=new ymaps.GeoObject({
	                         geometry: {
	                                    type: "LineString",
	                                    coordinates: arr_linkCoordinates[k]
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
	                                    strokeWidth: arr_strokeWidth[k],
					    strokeStyle: arr_strokeStyle[k],
					    visible: false	// когда появится вторая вершина будет true
	                     });
 		 //console.log('edge0='+arr2[0]+' edge1='+arr2[1]+' Coords='+arr_linkCoordinates[k]);	
                   myLink.events.add('dblclick',function(event){ me.SetOptionsLink(myLink,event);});

	           me.LinksArray.add( myLink);
	         }//if      
	     }//for
           }//if	
    },	
	
    // Редактирование свойств метки
    SetOptionsHost: function(Host,event){
	var me=this;	
	//console.log('dblcklick host==>'+Host.properties.get('hostid'));
	//console.log('dblcklick host==>'+Host.properties.get('imageid'));
	  // HTML-содержимое контекстного меню.
         // Если меню метки уже отображено, то убираем его.
      if (jQuery('#menu').css('display') == 'block') { jQuery('#menu').remove();}
      else{
	 var TmpHost=new Object({
		preset	:	Host.options.get('preset'),		
		iconImageHref: Host.options.get('iconImageHref'),
		iconImageSize: Host.options.get('iconImageSize'),
		iconImageOffset: Host.options.get('iconImageOffset')
		});
     	 var menuContent =
                        '<div id="menu">\
			<div style="float:right"><img id="closebutton" src="images/general/error2.png" /> </div>\
                        <div align="center"> Ввод параметров Хоста</div>\
			<div id="imagePreview" style="float:left; width:30px;"></div>\
			<div style="margin-left: 40px; width:210px;">\
				<select name="icon_image" id="icon_image" class="inputbox" size="1">\
				</select>\
			</div>\
			<div align="center"><table align="center" border="0">\
				<tr><td colspan="2"><bold>Размер иконки</bold></td><tr>\
				<tr>\
				<td>Ширина</td><td>Высота</td>\
				</tr><tr>\
				<td><input type="text" name="w_image" /></td><td><input type="text" name="h_image" /></td>\
				</tr>\
                                <tr><td colspan="2"><bold>Смещение иконки</bold></td><tr>\
                                <tr>\
                                <td>Смещение X</td><td>Смещение Y</td>\
                                </tr><tr>\
                                <td><input type="text" name="x_offset" /></td><td><input type="text" name="y_offset" /></td>\
                                </tr>\
			     </table>\
			</div>\
                        <div align="center"><input type="submit" value="Сохранить" /></div>\
                        </div>'; 
               // Размещаем контекстное меню на странице
                  jQuery('body').append(menuContent);
		// Заполняем image
		var htmlSelect=document.getElementById("icon_image");
		opt = new Option("- Иконка по умолчанию - ", 0);
                opt.selected = "selected";
                htmlSelect.options.add(opt, 0);
		var maxlen=0;
		var imageid=Host.properties.get('imageid');
                for (var i = 0; i < me.ImagesArray.length; i++) {
                	opt = new Option(me.ImagesArray[i].name, me.ImagesArray[i].imageid);
			var len = me.ImagesArray[i].name.length
			if (len> maxlen){maxlen=len;}
                    if ( imageid == me.ImagesArray[i].imageid) {
                    	opt.selected = "selected";
                        jQuery('input[name="w_image"]').val(Host.options.get('iconImageSize')[0]);
                        jQuery('input[name="h_image"]').val(Host.options.get('iconImageSize')[1]);
                        jQuery('input[name="x_offset"]').val(Host.options.get('iconImageOffset')[0]);
                        jQuery('input[name="y_offset"]').val(Host.options.get('iconImageOffset')[1]);

                    }
		 htmlSelect.options.add(opt, i + 1);

                }
 	      // Задаем позицию меню.
	      jQuery('#menu').css({
                   left: event.get('position')[0]+10,
                   top: event.get('position')[1]+10,
		   width: maxlen+'em'
               });
		// кнопка закрытия формы	
		jQuery('#closebutton').click(function() {
			jQuery('#menu').remove();
		});
	
		jQuery('#icon_image').change(function() {
			jQuery("#imagePreview").empty();
			if (jQuery("#icon_image").val()!="0" ){
				jQuery("#imagePreview").append("<img id=\"MyImg\" src=\"imgstore.php?iconid=" + jQuery("#icon_image").val() + "\" />");
				jQuery("#MyImg").load(function() {
					//console.log('Size: width:'+jQuery("#MyImg").width()+"  height: "+jQuery("#MyImg").height());	
					//Подгоняем размер картинки (для красоты)

					// Расчитываем коээфициент масштабирования иконки
					var k_width=jQuery("#MyImg").width()/40;
 					var k_height=jQuery("#MyImg").height()/40;
					var koef=Math.max(k_width,k_height);

					jQuery('#MyImg').css({
						width:  jQuery("#MyImg").width()/koef,
						height: jQuery("#MyImg").height()/koef
					});
					 jQuery('input[name="w_image"]').val(jQuery("#MyImg").width());
					 jQuery('input[name="h_image"]').val(jQuery("#MyImg").height());
					 jQuery('input[name="x_offset"]').val(0);
					 jQuery('input[name="y_offset"]').val(-jQuery("#MyImg").height());
				});
			}
			else{
				jQuery("#imagePreview").append("displays image here");
				Host.options.set('preset', "twirl#blueIcon");
			}
                });

		jQuery('#menu input[type="submit"]').click(function () {
			if (jQuery("#icon_image").val()!="0" ){
				Host.properties.set('imageid',jQuery("#icon_image").val());
				Host.options.set({
					iconImageHref:  'imgstore.php?iconid=' + Host.properties.get('imageid'),
					iconImageSize: [jQuery('input[name="w_image"]').val(),jQuery('input[name="h_image"]').val()],
					iconImageOffset:[jQuery('input[name="x_offset"]').val(),jQuery('input[name="y_offset"]').val()]
				});
			}
			else{  // ставим назад стандартную метку
				Host.options.unset(['iconImageHref','iconImageSize','iconImageOffset']);
				Host.properties.unset('imageid');
				Host.options.set('preset' , 'twirl#blueIcon');
			}

			//console.log(Host);
			//console.log('id='+Host.properties.get('hostid')+'   Coords:'+Host.geometry.getCoordinates());
			// Заносим данные  в LinksChange (так проще)для записи изменений в базу Zabbix
			 var id0=Host.properties.get('hostid');
			 var flag_ins = true;	
			 for (var j=0;j<me.LinksChange.length;j++){
	    		         if (me.LinksChange[j]==id0) {flag_ins=false;}
		         }  
			if (flag_ins==true){me.LinksChange.push(id0);}
		
			console.log(me.LinksChange);
			/*
	                if (me.saved == false) {
                   	    	me.saved = true;
	      	                me.SaveButton.enable();
                       		me.SaveButton.events.add('click', function() { 
					me.save_change(); 
				});
		        }
			*/
			me.save_change_another();
		        jQuery('#menu').remove();

		});
      } //else
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
							hintContent : ''+ out.result[i].name+' <br> '+out.result[i].hostid+ ' <br > <font color="red">Сделайте dblclick на хосте для редактирования его свойств</font>',
							hostid : out.result[i].hostid
						},
						{
							draggable : true,
							preset : iconPreset
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
					 me.Hosts[i].properties.set('imageid',ImagesArray[0].imageid);
                                         me.Hosts[i].options.set({
                                                        iconImageHref:  'imgstore.php?iconid=' +ImagesArray[0].imageid,
                                                        iconImageSize:  ImagesArray[0].iconImageSize,
                                                        iconImageOffset:ImagesArray[0].iconImageOffset
                                         });
				  }//if
				}
				// san ->
				//console.log(me.Hosts[i]);
				(function(i) {
					me.Hosts[i].events.add('dragend', function() {
							me.draghost(
								me.Hosts[i].properties.get('hostid'),	
								me.Hosts[i].geometry.getCoordinates()
							);
						});
					me.Hosts[i].events.add('dblclick', function(event) {
						if (me.AddLinkButton.isSelected()==true){
							if (me.my_count<=1){
								me.tmp_placemarks[me.my_count]= me.Hosts[i];
								
								//me.Hosts[i].options.set('preset' , 'twirl#redIcon');
								me.Hosts[i].options.set('visible',false);
								me.TmpLabel[me.my_count].geometry.setCoordinates(me.Hosts[i].geometry.getCoordinates());
								me.TmpLabel[me.my_count].options.set('visible',true);
							        me.Map.geoObjects.add(me.TmpLabel[me.my_count]);

	
								if (me.my_count==1){
								    me.TmpLabel[0].options.set('visible',false);	
								    me.TmpLabel[1].options.set('visible',false);
								    me.Map.geoObjects.remove(me.TmpLabel);
	
								   if ( me.tmp_placemarks[0].properties.get('hostid')!=me.tmp_placemarks[1].properties.get('hostid')){	
									 me.AddNewLink();
								   }
	                                                                 me.tmp_placemarks[0].options.set('visible' , true);
                                                                         me.tmp_placemarks[1].options.set('visible' , true);

									// изврат
									 me.my_count=-1;
								}
								me.my_count=me.my_count+1;
							}
						    return;
						}
						if (me.DelLinkButton.isSelected()==true){
                                                        if (me.my_count<=1){
                                                                me.tmp_placemarks[me.my_count]= me.Hosts[i];
                                                                //me.Hosts[i].options.set('preset' , 'twirl#redIcon');
								me.Hosts[i].options.set('visible',false);
								me.TmpLabel[me.my_count].geometry.setCoordinates(me.Hosts[i].geometry.getCoordinates());
								me.TmpLabel[me.my_count].options.set('visible',true);
                                                                me.Map.geoObjects.add(me.TmpLabel[me.my_count]);


                                                                if (me.my_count==1){
                                                                   me.TmpLabel[0].options.set('visible',false);        
                                                                   me.TmpLabel[1].options.set('visible',false);
								    me.Map.geoObjects.remove(me.TmpLabel);
								   if ( me.tmp_placemarks[0].properties.get('hostid')!=me.tmp_placemarks[1].properties.get('hostid')){	
                                                                         me.DelLink();
								   }
                                                                         me.tmp_placemarks[0].options.set('visible' , true);
                                                                         me.tmp_placemarks[1].options.set('visible' , true);

                                                                        // изврат
                                                                         me.my_count=-1;
                                                                }
                                                                me.my_count=me.my_count+1;
                                                        }
						    return;
						}
						    // просто двойной клик на хосте 
						    me.SetOptionsHost(me.Hosts[i],event);
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
