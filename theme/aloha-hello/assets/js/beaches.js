/* CSS owns layout/swipe; JS only adds optional controls and keyboard navigation. */
(() => {
 'use strict';
 function init() {
  document.querySelectorAll('[data-aloha-beaches].is-carousel').forEach(root => {
   const track=root.querySelector('.aloha-beaches-track'),cards=[...track.children],controls=root.querySelector('.aloha-beaches-controls');
   if (track.dataset.enhanced) return; track.dataset.enhanced='true'; controls.hidden=false;
   const previous=controls.querySelector('[data-beaches-direction="-1"]'),next=controls.querySelector('[data-beaches-direction="1"]'),status=controls.querySelector('.aloha-beaches-status');
   const update=()=>{
    const box=track.getBoundingClientRect(),visible=cards.map((c,i)=>({r:c.getBoundingClientRect(),i})).filter(c=>c.r.right>box.left+2&&c.r.left<box.right-2);
    previous.disabled=track.scrollLeft<=2;next.disabled=track.scrollLeft>=track.scrollWidth-track.clientWidth-2;
    if(visible.length)status.textContent=(visible[0].i+1)+'–'+(visible.at(-1).i+1)+' / '+cards.length;
   };
   const scroll=left=>track.scrollTo({left,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
   controls.addEventListener('click',e=>{const button=e.target.closest('[data-beaches-direction]');if(button&&!button.disabled)scroll(track.scrollLeft+Number(button.dataset.beachesDirection)*(track.clientWidth+24));});
   track.addEventListener('keydown',e=>{
    if(e.target!==track||e.altKey||e.ctrlKey||e.metaKey)return;
    const step=cards[0].getBoundingClientRect().width+24;
    if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();scroll(track.scrollLeft+(e.key==='ArrowRight'?step:-step));}
    if(e.key==='Home'||e.key==='End'){e.preventDefault();scroll(e.key==='Home'?0:track.scrollWidth);}
   });
   let pending=false;track.addEventListener('scroll',()=>{if(!pending){pending=true;requestAnimationFrame(()=>{pending=false;update();});}},{passive:true});
   new ResizeObserver(update).observe(track);update();
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
