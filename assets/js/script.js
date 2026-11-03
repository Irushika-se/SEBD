document.addEventListener('DOMContentLoaded',function(){
  var b=document.querySelector('.burger'),n=document.getElementById('nav');
  if(b&&n)b.addEventListener('click',function(){n.classList.toggle('open')});
  document.querySelectorAll('[data-confirm]').forEach(function(e){
    var ev=e.tagName==='FORM'?'submit':'click';
    e.addEventListener(ev,function(x){var m=e.dataset.confirm,sel=e.querySelector('select[name=outcome]');if(sel)m=m.replace('{outcome}',sel.options[sel.selectedIndex].text.toUpperCase());if(!confirm(m))x.preventDefault()});
  });
  document.querySelectorAll('.alert').forEach(function(a){setTimeout(function(){a.style.display='none'},7000)});
  // role switch on register page
  var rs=document.querySelectorAll('input[name=role]');
  function sw(){var v=document.querySelector('input[name=role]:checked');if(!v)return;
    document.querySelectorAll('[data-for]').forEach(function(s){var on=s.dataset.for===v.value;s.style.display=on?'':'none';
      s.querySelectorAll('input,select,textarea').forEach(function(i){i.disabled=!on})})}
  rs.forEach(function(r){r.addEventListener('change',sw)});sw();
  // optional precise location (browser geolocation); the server validates and rounds it
  document.querySelectorAll('[data-geo]').forEach(function(btn){
    var box=btn.closest('[data-geobox]'),la=box.querySelector('[name=geo_lat]'),ln=box.querySelector('[name=geo_lng]'),out=box.querySelector('.geo-status');
    btn.addEventListener('click',function(){
      if(!navigator.geolocation){out.textContent='This browser cannot share a location. Your city centre will be used.';return;}
      out.textContent='Getting your location...';
      navigator.geolocation.getCurrentPosition(function(p){
        la.value=p.coords.latitude.toFixed(5);ln.value=p.coords.longitude.toFixed(5);
        out.textContent='Location captured (accuracy about '+Math.round(p.coords.accuracy)+' m). It is saved when you submit the form.';
      },function(e){la.value='';ln.value='';out.textContent='Could not get your location ('+e.message+'). Your city centre will be used.';},
      {enableHighAccuracy:true,timeout:10000,maximumAge:60000});
    });
  });
  // live table search
  var q=document.getElementById('tsearch');
  if(q)q.addEventListener('input',function(){var t=q.value.toLowerCase();
    document.querySelectorAll('table.searchable tbody tr').forEach(function(r){r.style.display=r.textContent.toLowerCase().indexOf(t)>-1?'':'none'})});
});
