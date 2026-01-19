//onload
//initializes variables required by the config button
function onload(){
	//variables for The config Function
	_currentSide = 1;
	mainView = document.getElementById("mainView");
	configView = document.getElementById("configView");
	//set standard size for the widget (defines the size of the widget, same as specified in config.xml - this has higher priority)
	//Also available are :
		// window.resizeBy(xValue, yValue) - resizes the widget window by the values given
		// window.innerWidth - returns the current width of the widget window
		// window.innerHeight - returns the current height of the widget window
	window.resizeTo(400,400);
	//---
	//Add onload load events after this line:
	
	

}
//--------------------Add your code after this line-------------------------

$(document).ready(function(){

	// Local copy of jQuery selectors, for performance.
	var jpPlayTime = $("#jplayer_play_time");
	var jpTotalTime = $("#jplayer_total_time");

	$("#jquery_jplayer").jPlayer({
		ready: function () {
			this.element.jPlayer("setFile", "http://stream3.jenny.fm:8000/;stream/1", "http://radio.hbr1.com:19800/trance.ogg").jPlayer("play");
		},
		oggSupport: true
	})
        .jPlayer("onProgressChange", function(loadPercent, 
playedPercentRelative, playedPercentAbsolute, playedTime, totalTime) { 
                jpPlayTime.text($.jPlayer.convertTime(playedTime)); 
                var mbTotalSec = playedTime / 1000; 
                var mbTotal = ((mbTotalSec * 64) / 8192).toFixed(1); 
                $("#jplayer_buffering").html(' |&nbsp;' + mbTotal + 'MB'); 
        })
	.jPlayer("onSoundComplete", function() {
		this.element.jPlayer("play");
	});
});

 function change_image() {
   document.all.myiframe.src="js/vis2.html";
}

var dayarray=new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday")
var montharray=new Array("January","February","March","April","May","June","July","August","September","October","November","December")

function getthedate(){
var mydate=new Date()
var year=mydate.getYear()
if (year < 1000)
year+=1900
var day=mydate.getDay()
var month=mydate.getMonth()
var daym=mydate.getDate()
if (daym<10)
daym="0"+daym
var hours=mydate.getHours()
var minutes=mydate.getMinutes()
var seconds=mydate.getSeconds()
var dn="AM"
if (hours>=12)
dn="PM"
if (hours>12){
hours=hours-12
}
if (hours==0)
hours=12
if (minutes<=9)
minutes="0"+minutes
if (seconds<=9)
seconds="0"+seconds
//change font size here
var cdate=""+hours+":"+minutes+":"+seconds+""+dn
+"&nbsp;"
if (document.all)
document.all.clock.innerHTML=cdate
else if (document.getElementById)
document.getElementById("clock").innerHTML=cdate
else
document.write(cdate)
}
if (!document.all&&!document.getElementById)
getthedate()
function goforit(){
if (document.all||document.getElementById)
setInterval("getthedate()",1000)
}


//----------------------do not edit below this line----------------------------
//The Toggle Function to show the config and main views of the widget
function toggleSide(){
	if(_currentSide == 0){
		mainView.style.display = "none";
		configView.style.display = "block";
		_currentSide = 1;
	} else {
		mainView.style.display = "block";
		configView.style.display = "none";
		_currentSide = 0;
	}
}