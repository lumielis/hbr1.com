// --------------------------------------------------------------------
// Cross Browser Layer Handler
// supports NN4, IE5+, NS and DOM compatible
//
// Copyright 2001 by Christian Mueller
// Email: christian.mueller@europe.com
//
// License Agreement
// 
// Except as specifically authorized from me, you may not make copies
// of Software, other than a single copy of Software for archival
// purposes. Unless enforcement is prohibited by applicable law, you
// may not modify or publish this Software. 
// --------------------------------------------------------------------

function Layer( id ) {
  
  this.VERSION = '1.03';
  
  this.setContent = _setContent;
  this.setBounds = _setBounds;
  this.getBounds = _getBounds;
  this.setPosition = _setPosition;
  this.setSize = _setSize;
  this.getLeft = _getLeft;
  this.getTop = _getTop;
  this.getWidth = _getWidth;
  this.getHeight = _getHeight;
  this.hide = _hide;
  this.show = _show;
  this.setZIndex = _setZIndex;
  this.getZIndex = _getZIndex;
  this.available = _avail;
  this.retBounds = _retBounds;
  
  this.BS = document.getElementById ? 1 : document.layers ? 2 : 0;
  this.id = id;
  this.layer = _getLayer( id );
  if( this.layer && this.layer.innerHTML )
    this.content = this.layer.innerHTML;
  else
    this.content = '';
  this.retBounds();
  
  function _setContent( content ) {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.innerHTML = content;
      break;
    case 2: // NN
      this.layer.document.open();
      this.layer.document.write( content );
      this.layer.document.close();
      break;
    }
  }
  
  function _setBounds( x, y, w, h ) {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.style.left = x;
      this.layer.style.top = y;
      this.layer.style.width = w;
      this.layer.style.height = h;
      break;
    case 2: // NN
      this.layer.resizeTo( w, h );
      this.layer.pageX = x;
      this.layer.pageY = y;
      break;
    }
    this.retBounds();
  }
  
  function _getBounds() {
    return new Array( this.left, this.top, this.width, this.height );
  }
  
  function _getLeft() {
    return this.left;
  }
  
  function _getTop() {
    return this.top;
  }
  
  function _getWidth() {
    return this.width;
  }
  
  function _getHeight() {
    return this.height;
  }
  
  function _setPosition( x, y ) {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.style.left = x;
      this.layer.style.top = y;
      break;
    case 2: // NN
      this.layer.pageX = x;
      this.layer.pageY = y;
      break;
    }
    this.retBounds();
  }
  
  function _setSize( w, h ) {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.style.width = w;
      this.layer.style.height = h;
      break;
    case 2: // NN
      this.layer.resizeTo( w, h );
      break;
    }
    this.retBounds();
  }
  
  function _hide() {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.style.visibility = "hidden";
      return;
    case 2: // NN
      this.layer.visibility = "hidden";
      return;
    }
  }
  
  function _show() {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.style.visibility = "visible";
      return;
    case 2: // NN
      this.layer.visibility = "visible";
      return;
    }
  }
  
  function _setZIndex( index ) {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.layer.style.zIndex = index;
      return;
    case 2: // NN
      this.layer.zIndex = index;
      return;
    }
  }
  
  function _getZIndex() {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      return this.layer.style.zIndex;
    case 2: // NN
      return this.layer.zIndex;
    }
  }
  
  function _avail() {
    return this.BS > 0;
  }
  
  // private functions
  
  function _retBounds() {
    if( ! this.layer )
      if( ( this.layer = _getLayer( this.id ) ) == null )
        return false;
    switch( this.BS ) {
    case 1: // DOM
      this.left = this.layer.offsetLeft;
      this.top = this.layer.offsetTop;
      this.width = this.layer.offsetWidth;
      this.height = this.layer.offsetHeight;
      break;
    case 2: // NN
      this.left = this.layer.pageX;
      this.top = this.layer.pageY;
      this.width = this.layer.document.width;
      this.height = this.layer.document.height;
      break;
    }
  }
  
  function _getLayer( id ) {
    if( document.getElementById )
      return document.getElementById( id );
    else if( document.layers )
      return document.layers[id];
    else
      return null;
  }
  
}
