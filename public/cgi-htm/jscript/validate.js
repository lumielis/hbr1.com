function validateForm( form ) {
  if( form == null ) return true;
  if( document.forms[form] ) form = document.forms[form];
  if( form.elements == null ) {
    alert( 'The form parameter is not a form object, form name or form index!' );
    return false;
  }
  for( var i = 0; i < form.elements.length; i ++ ) {
    if( form.elements[i].name.match( /_v_msg_(.+)/ ) ) {
      var element = RegExp.$1;
      switch( form.elements[element].type ) {
      case 'text':
      case 'password':
        if( form.elements[element].value == '' ) {
          alert( form.elements[i].value );
          return false;
        }
        if( form.elements['_v_exp_'+element] ) {
          if( ! form.elements[element].value.match( form.elements['_v_exp_'+element].value ) ) {
            if( form.elements['_v_mxp_'+element] )
              alert( form.elements['_v_mxp_'+element].value );
            else
              alert( form.elements[i].value );
            return false;
          }
        }
        break;
      case 'radio':
        var ok = false;
        for( var j = 0; j < form.elements[element].length; j ++ ) {
          if( form.elements[element][j].checked ) {
            ok = true;
            break;
          }
        }
        if( ! ok ) {
          alert( form.elements[i].value );
          return false;
        }
        break;
      case 'select':
        if( form.elements[element].options.selectedIndex < 0 ) {
          alert( form.elements[i].value );
          return false;
        }
        break;
      case 'checkbox':
        if( ! form.elements[element].checked ) {
          alert( form.elements[i].value );
          return false;
        }
        break;
      default:
        //alert( form.elements[element].type );
        break;
      }
    }
  }
  return true;
}
