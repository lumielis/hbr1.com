function moneyChanged( owner ) {
  if( ! owner ) return;
  var money = owner.value;
  money = money.replace( /\./g, '' );
  if( money.search( /(.*),(.*)/ ) > -1 ) {
    money = RegExp.$1 + '' + RegExp.$2;
    if( RegExp.$2.length < 1 ) money += '0';
    if( RegExp.$2.length < 2 ) money += '0';
  }
  var clients = 0;
  while( true ) {
    if( owner.form.elements['draw_prize'+clients] ) clients ++;
    else break;
  }
  if( ! clients ) return;
  if( DivideByClients )
    money = Math.round( Number( money ) / clients );
  clients --;
  while( clients >= 0 ) {
    if( owner.form.elements['draw_prize'+clients] ) {
      if( owner.form.elements['right'+clients] )
        owner.form.elements['draw_prize'+clients].value = inputToMoney( money * owner.form.elements['right'+clients].value );
      else
        owner.form.elements['draw_prize'+clients].value = inputToMoney( money );
      clients --;
    }
    else break;
  }
}

function updateMoney( owner ) {
  id = 'commission_amount';
  var prize = 0;
  var clients = 0;
  while( true ) {
    if( owner.form.elements['draw_prize'+clients] ) {
      prize += Number( moneyToInput( owner.form.elements['draw_prize'+clients].value ) );
      clients ++;
    }
    else break;
  }
  owner.form.elements[id].value = inputToMoney( prize );
}

function moneyToInput( money ) {
  money = money.replace( /\./, '' );
  if( money.search( /(.*)\,(.*)/ ) != -1 ) {
    money = RegExp.$1 + '' + RegExp.$2;
    if( RegExp.$2.length < 1 ) money += '0';
    if( RegExp.$2.length < 2 ) money += '0';
  }
  else {
    money += '00';
  }
  return money;
}

function inputToMoney( money, flag ) {
  money = ( money / 100 ) + '';
  money = money.replace( /\./, ',' );
  if( money.search( /(.*)\,(.*)/ ) < 0 ) {
    money += ',00';
  }
  else {
    money = '';
    for( var i = RegExp.$1.length - 3; i > 0; i -= 3 ) {
      money = '.' + RegExp.$1.substr( i, 3 ) + money;
    }
    if( ( RegExp.$1.length % 3 ) == 0 )
      money = RegExp.$1.substr( 0, 3 ) + money;
    else
      money = RegExp.$1.substr( 0, ( RegExp.$1.length % 3 ) ) + money;
    money += ',' + RegExp.$2.substr( 0, 2 );
    if( RegExp.$2.length < 1 ) money += '0';
    if( RegExp.$2.length < 2 ) money += '0';
  }
  if( ! flag && money.search( /-/ ) == -1 ) money = '+' + money;
  return money;
}

function setDivide( owner ) {
  if( ! owner ) return;
  DivideByClients = owner.checked;
}

var DivideByClients = false;