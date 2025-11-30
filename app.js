function toggleAll(masterCheckbox, tableId) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const checkboxes = table.querySelectorAll('tbody input[type="checkbox"]');
  checkboxes.forEach((cb) => {
    cb.checked = masterCheckbox.checked;
  });
}

function exportSelected(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return;

  const headerCells = Array.from(table.querySelectorAll('thead th')).slice(1);
  const headers = headerCells.map((cell) => cell.textContent.trim());

  const selectedRows = Array.from(table.querySelectorAll('tbody tr')).filter((row) => {
    const checkbox = row.querySelector('input[type="checkbox"]');
    return checkbox && checkbox.checked;
  });

  if (!selectedRows.length) {
    alert('Select at least one row to download.');
    return;
  }

  const csv = [headers.join(',')];

  selectedRows.forEach((row) => {
    const cells = Array.from(row.querySelectorAll('td')).slice(1);
    const values = cells.map((cell) => {
      const text = cell.textContent.trim().replace(/"/g, '""');
      return `"${text}"`;
    });
    csv.push(values.join(','));
  });

  const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${tableId}-selection.csv`;
  link.click();
  URL.revokeObjectURL(url);
}