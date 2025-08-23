/**
 * Planimetria con arco e denti verticali.
 * Hotfix: usa i numeri reali restituiti dall’API (nessuna assunzione 1..N).
 * Il numero dei denti = numero slot dal DB; ordine sinistra→destra per numero_esterno ASC.
 */
(function(){
  const colorByStatus = {
    "Libero":"#28a745",
    "Occupato":"#dc3545",
    "Riservato":"#ffc107",
    "Manutenzione":"#6c757d"
  };

  async function renderDock(options){
    const container = document.getElementById(options.containerId);
    if(!container) return;
    container.innerHTML = '';

    // Carica stato real-time dagli slot
    let data = [];
    try {
      const resp = await fetch(options.apiUrl, { headers: {'Accept':'application/json'} });
      data = await resp.json();
    } catch(e){
      console.error('Errore fetch stato slot:', e);
      data = [];
    }

    if (!Array.isArray(data) || data.length === 0) {
      container.innerHTML = '<div class="alert alert-warning mb-0">Nessun dato disponibile per la mappa.</div>';
      return;
    }

    // Ordina per numero_esterno crescente
    data.sort((a,b) => Number(a.numero_esterno) - Number(b.numero_esterno));

    const slots = data.length;
    const w = 1000, h = 360;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    svg.classList.add('dock');

    // Arco
    const path = document.createElementNS(svg.namespaceURI, 'path');
    path.setAttribute('d', `M 50 ${h-50} Q ${w/2} ${h-260} ${w-50} ${h-50}`);
    path.setAttribute('fill','none');
    path.setAttribute('stroke','#96c7e6');
    path.setAttribute('stroke-width','20');
    svg.appendChild(path);

    // Denti posizionati lungo l'arco (spaziati uniformemente)
    const startX = 90, endX = w - 90;
    const baseY = h - 70;
    const denom = Math.max(1, slots - 1);

    for (let i=0; i<slots; i++){
      const slot = data[i];
      const num = Number(slot.numero_esterno);
      const t = i/denom;
      const x = startX + t*(endX-startX);
      const curveY = baseY - 140*Math.sin(Math.PI * t);
      const length = 70;

      const g = document.createElementNS(svg.namespaceURI, 'g');

      const rect = document.createElementNS(svg.namespaceURI, 'rect');
      rect.setAttribute('x', x-5);
      rect.setAttribute('y', curveY-length);
      rect.setAttribute('width', 10);
      rect.setAttribute('height', length);
      rect.setAttribute('rx', 2);
      rect.setAttribute('fill', colorByStatus[slot.stato] || '#6c757d');
      rect.classList.add('dock-slot');

      const title = document.createElementNS(svg.namespaceURI, 'title');
      title.textContent = `Posto ${num} • ${slot.stato}${slot.proprietario ? ' • ' + slot.proprietario : ''}`;
      rect.appendChild(title);

      rect.addEventListener('click', () => {
        if (slot.id) window.location.href = `/app/slots/view.php?id=${slot.id}`;
      });

      g.appendChild(rect);

      const text = document.createElementNS(svg.namespaceURI, 'text');
      text.setAttribute('x', x);
      text.setAttribute('y', curveY - length - 8);
      text.setAttribute('text-anchor','middle');
      text.setAttribute('font-size','12');
      text.setAttribute('fill','#333');
      text.textContent = String(num);
      g.appendChild(text);

      svg.appendChild(g);
    }

    container.appendChild(svg);

    const legend = document.createElement('div');
    legend.className = 'dock-legend mt-2';
    legend.innerHTML = `
      <span class="libero">Libero</span>
      <span class="occupato">Occupato</span>
      <span class="riservato">Riservato</span>
      <span class="manutenzione">Manutenzione</span>
    `;
    container.appendChild(legend);
  }

  window.renderDock = renderDock;
})();
