var xmlhttp;
var spanid;
function sort(id, type, one, two)
{
  xmlhttp=GetXmlHttpObject();
  if (xmlhttp==null) {
    alert ("Your browser does not support XMLHTTP!");
    return;
  }
  spanid = id;
  var url="async/async_sort.php";
  var parameters="type="+encodeURIComponent(type)+
  		"&id1="+encodeURIComponent(one)+"&id2="+encodeURIComponent(two);
  xmlhttp.onreadystatechange=sortStateChanged;
  xmlhttp.open("POST",url,true);
  xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xmlhttp.setRequestHeader("Content-length", parameters.length);
  xmlhttp.setRequestHeader("Connection", "close");
  xmlhttp.send(parameters);
  var element = document.getElementById(spanid);
  element.innerHTML = "Rearranging elements, please wait...";
}
function GetXmlHttpObject()
{
  if (window.XMLHttpRequest) {
    // code for IE7+, Firefox, Chrome, Opera, Safari
    return new XMLHttpRequest();
  }
  if (window.ActiveXObject) {
    // code for IE6, IE5
    return new ActiveXObject("Microsoft.XMLHTTP");
  }
  return null;
}

function sortStateChanged()
{
if (xmlhttp.readyState==4)
  {
    var element = document.getElementById(spanid);
    var response = xmlhttp.responseText;
    element.innerHTML = response;
  }
}