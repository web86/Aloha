/** Installed only by browser tests; production always uses the Maps SDK. */
module.exports = function mockMaps() {
    window.catalogMapCreations = 0;
    window.catalogMapState = {zoom:13, instances:0, zoomCalls:0, infoOpen:false, anchored:false, resizeCalls:0};
    class Map {
        constructor(surface) { this.surface=surface; window.catalogMapCreations++; window.catalogMapState.instances++; }
        fitBounds() {} setCenter() {}
        getZoom(){return window.catalogMapState.zoom;}
        setZoom(zoom){window.catalogMapState.zoom=zoom;window.catalogMapState.zoomCalls++;}
    }
    let markerIndex=0;
    class AdvancedMarkerElement {
        constructor({map,position}) {this.position=position;this.element=document.createElement('div');Object.assign(this.element.style,{position:'absolute',left:'20px',top:(150+markerIndex++*70)+'px'});map.surface.append(this.element);}
        append(element){this.element.append(element);}
    }
    class LatLngBounds {extend(){}}
    class InfoWindow {
        constructor(options){this.options=options;this.listeners={};}
        setOptions(options){Object.assign(this.options,options);}
        setContent(content){this.content=content;}
        addListener(name,callback){this.listeners[name]=callback;}
        open({map,anchor,shouldFocus}){
            if(this.wrapper)this.wrapper.remove();
            this.wrapper=document.createElement('div');this.wrapper.className='gm-style-iw-c';
            Object.assign(this.wrapper.style,{position:'absolute',top:'90px',left:'20px',maxWidth:'calc(100% - 40px)',zIndex:10});
            this.wrapper.append(this.content);map.surface.append(this.wrapper);
            window.catalogMapState.infoOpen=true;window.catalogMapState.anchored=anchor instanceof AdvancedMarkerElement;
            if(shouldFocus)this.content.querySelector('a')?.focus({preventScroll:true});
            this.listeners.visible?.();
        }
        close(){this.wrapper?.remove();window.catalogMapState.infoOpen=false;this.listeners.close?.();}
    }
    window.google={maps:{event:{trigger(){window.catalogMapState.resizeCalls++;}},importLibrary:async name=>({maps:{Map,InfoWindow},marker:{AdvancedMarkerElement},core:{LatLngBounds}}[name])}};
};
