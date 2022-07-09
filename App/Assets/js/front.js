const inmap_maps = [];

const inmap_create_map = function(map_hash = null, map_geojson = null) {
	if(! map_hash || ! map_geojson || ! jQuery) {
		return false;
	}
	
	var map_id = 'inmap-' + map_hash;
	
	//CreateMap
	var map_jq = jQuery('#' + map_id);
	var map_l = L.map(map_id);

	//Make accessible
	map_jq.data('map_l', map_l)
	inmap_maps[map_hash] = map_l;
	
	//UI
	var info_jq = jQuery('<div />')
		.attr({})
		.addClass('inmap-info')
	;
	
	map_jq.append(info_jq);
	
	//Basemap
	var tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
	}).addTo(map_l);

	//Data layer
	var data_layer = L.geoJSON(map_geojson, {
		//Read style from GeoJSON
		style: function(feature) {
			if(typeof feature.properties.style === 'object') {
				return feature.properties.style;
			}
		},
		
		//Marker Icons
		pointToLayer: function (feature, latlng) {
			if(typeof feature.properties.style === 'object') {
				var icon = L.divIcon(feature.properties.icon);
				return L.marker(latlng, {
					icon: icon
				});		
			} else {
				return L.marker(latlng);					
			}				
		},
		
		//Events
		onEachFeature: function(feature, layer) {
			//Description?
			if(typeof feature.properties.description === 'string') {
			
			}
		}
	});
	
	//Add
	data_layer.addTo(map_l);
	
	//Events
	data_layer.on('click', function(e) {
		var feature = e.layer.feature;
		var target_jq = jQuery(e.originalEvent.target);
		
		//Description?
		if(typeof feature.properties.description === 'string') {
			//Get target
			if(! target_jq.hasClass('inmap-marker-icon')) {
				target_jq = target_jq.parents('inmap-marker-icon');		
		
				if(! target_jq.length) {
					return false;
				}
			}
			
			var markers = jQuery('.inmap-marker-icon');
			markers.removeClass('inmap-active');
			
			//
			target_jq.addClass('inmap-active');			
 			info_jq.html(feature.properties.description);
		}		
	});
	
	map_l.fitBounds(data_layer.getBounds());
	
};