document.addEventListener('DOMContentLoaded', () => {
    // Lógica del modal de selección de usuario
    const selectionModalEl = document.getElementById('modalSeleccionarUsuario');
    if (selectionModalEl) {
        const userSearchInput = document.getElementById('user-search-input');
        const userListItems = selectionModalEl.querySelectorAll('.user-select-item');
        const dateInput = document.getElementById('corte-date-input');
        const generateBtn = document.getElementById('generate-corte-btn');
        let selectedUserId = null;

        userSearchInput.addEventListener('input', () => {
            const searchTerm = userSearchInput.value.toLowerCase();
            userListItems.forEach(item => {
                const userName = item.textContent.toLowerCase();
                item.style.display = userName.includes(searchTerm) ? '' : 'none';
            });
        });

        userListItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                userListItems.forEach(el => el.classList.remove('active'));
                item.classList.add('active');
                selectedUserId = item.dataset.userId;
                generateBtn.disabled = false;
            });
        });

        generateBtn.addEventListener('click', () => {
            if (selectedUserId) {
                const selectedDate = dateInput.value;
                const url = new URL(window.location.pathname, window.location.origin);
                url.searchParams.set('usuarioId', selectedUserId);
                url.searchParams.set('fecha', selectedDate);
                window.location.href = url.toString();
            }
        });
    }

    // Lógica de cálculo en tiempo real del corte
    const formCorte = document.getElementById('form-corte-caja');
    if (formCorte) {
        const montoInicialInput = document.getElementById('corte_caja_efectivo_inicial');
        const montoFinalInput = document.getElementById('corte_caja_efectivo_final');
        const totalEfectivoCalculado = parseFloat(document.getElementById('total-efectivo-calculado')?.textContent.replace(/[^0-9.-]+/g,"")) || 0;
        
        // --- CORRECCIÓN CLAVE ---
        // Tomamos el valor absoluto para evitar el doble negativo, ya que el texto es "-$500.00"
        const totalGastosCalculado = Math.abs(parseFloat(document.getElementById('total-gastos-calculado')?.textContent.replace(/[^0-9.-]+/g,""))) || 0;
        
        const totalCajaSpan = document.getElementById('total-en-caja');
        const diferenciaSpan = document.getElementById('diferencia-corte');
        
        const calcularCorte = () => {
            const inicial = parseFloat(montoInicialInput.value) || 0;
            const finalContado = parseFloat(montoFinalInput.value) || 0;
            
            // --- FÓRMULA CORREGIDA ---
            // Ahora la fórmula es correcta: Inicial + Ingresos - Gastos
            const totalEsperadoEnCaja = inicial + totalEfectivoCalculado - totalGastosCalculado;
            
            const diferencia = finalContado - totalEsperadoEnCaja;

            if(totalCajaSpan) totalCajaSpan.textContent = `$${totalEsperadoEnCaja.toFixed(2)}`;
            if(diferenciaSpan) {
                // Limpia el contenido anterior antes de añadir el nuevo
                diferenciaSpan.textContent = `$${diferencia.toFixed(2)}`;
                diferenciaSpan.classList.remove('text-success', 'text-danger', 'text-muted');
                
                let textoSmall = '';
                if (diferencia > 0) {
                    diferenciaSpan.classList.add('text-success');
                    textoSmall = ' (Sobrante)';
                } else if (diferencia < 0) {
                    diferenciaSpan.classList.add('text-danger');
                    textoSmall = ' (Faltante)';
                } else {
                    diferenciaSpan.classList.add('text-muted');
                    textoSmall = ' (Cuadra)';
                }
                diferenciaSpan.innerHTML += ` <small>${textoSmall}</small>`;
            }
        };

        montoInicialInput.addEventListener('input', calcularCorte);
        montoFinalInput.addEventListener('input', calcularCorte);
        calcularCorte(); // Calcular al cargar
    }
    
    // Lógica del buscador del historial
    const buscadorCortes = document.getElementById('buscador-cortes');
    const tableBodyCortes = document.getElementById('cortes-table-body');
    if (buscadorCortes && tableBodyCortes) {
        buscadorCortes.addEventListener('input', e => {
            const termino = e.target.value.toLowerCase();
            const filas = tableBodyCortes.querySelectorAll('tr');
            filas.forEach(fila => {
                fila.style.display = fila.innerText.toLowerCase().includes(termino) ? 'table-row' : 'none';
            });
        });
    }
});