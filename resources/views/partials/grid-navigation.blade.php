<script>
document.addEventListener('keydown', function(e) {
    const keys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];
    if (!keys.includes(e.key)) return;

    const active = document.activeElement;
    if (!active || !(active.tagName === 'INPUT' || active.tagName === 'SELECT')) return;

    const td = active.closest('td');
    const tr = active.closest('tr');
    const table = active.closest('table');
    if (!td || !tr || !table) return;

    const colIndex = td.cellIndex;
    const rows = Array.from(table.rows);
    const rowIndex = rows.indexOf(tr);

    let nextInput = null;

    if (e.key === 'ArrowUp' && rowIndex > 0) {
        for (let i = rowIndex - 1; i >= 0; i--) {
            const targetTd = rows[i].cells[colIndex];
            if (targetTd) {
                nextInput = targetTd.querySelector('input:not([type="hidden"]), select');
                if (nextInput) break;
            }
        }
    } else if (e.key === 'ArrowDown' && rowIndex < rows.length - 1) {
        for (let i = rowIndex + 1; i < rows.length; i++) {
            const targetTd = rows[i].cells[colIndex];
            if (targetTd) {
                nextInput = targetTd.querySelector('input:not([type="hidden"]), select');
                if (nextInput) break;
            }
        }
    } else if (e.key === 'ArrowLeft') {
        let prevTd = td.previousElementSibling;
        while (prevTd) {
            nextInput = prevTd.querySelector('input:not([type="hidden"]), select');
            if (nextInput) break;
            prevTd = prevTd.previousElementSibling;
        }
    } else if (e.key === 'ArrowRight') {
        let nextTd = td.nextElementSibling;
        while (nextTd) {
            nextInput = nextTd.querySelector('input:not([type="hidden"]), select');
            if (nextInput) break;
            nextTd = nextTd.nextElementSibling;
        }
    }

    if (nextInput) {
        e.preventDefault();
        nextInput.focus();
        if (nextInput.tagName === 'INPUT' && typeof nextInput.select === 'function') {
            nextInput.select();
        }
    }
});
</script>
