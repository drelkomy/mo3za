// Custom Pagination Script for Filament
document.addEventListener('DOMContentLoaded', function () {
    // Check if we are in a Filament table context
    const tableElement = document.querySelector('[wire\\:model="table"]');
    if (!tableElement) return;

    const paginationContainer = document.querySelector('.filament-tables-pagination');
    if (!paginationContainer) return;

    const paginationList = paginationContainer.querySelector('.filament-tables-pagination-list');
    if (!paginationList) return;

    // Get pagination data from Filament via Livewire
    const wireId = tableElement.getAttribute('wire:id');
    const paginator = window.Livewire.find(wireId);
    if (!paginator || !paginator.paginator) {
        console.error('Paginator not found');
        return;
    }

    const currentPage = paginator.paginator.current_page;
    const lastPage = paginator.paginator.last_page;

    // Clear existing pagination items
    paginationList.innerHTML = '';

    // Add Previous button
    const prevButton = document.createElement('a');
    prevButton.classList.add('filament-tables-pagination-previous');
    prevButton.textContent = 'السابق';
    if (currentPage === 1) {
        prevButton.classList.add('filament-tables-pagination-disabled');
        prevButton.style.pointerEvents = 'none';
    } else {
        prevButton.href = '#';
        prevButton.addEventListener('click', (e) => {
            e.preventDefault();
            paginator.gotoPage(currentPage - 1);
        });
    }
    paginationList.appendChild(prevButton);

    // Add page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(lastPage, startPage + maxVisiblePages - 1);

    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const pageItem = document.createElement('a');
        pageItem.classList.add('filament-tables-pagination-item');
        pageItem.textContent = i;
        if (i === currentPage) {
            pageItem.classList.add('filament-tables-pagination-current');
            pageItem.style.pointerEvents = 'none';
        } else {
            pageItem.href = '#';
            pageItem.addEventListener('click', (e) => {
                e.preventDefault();
                paginator.gotoPage(i);
            });
        }
        paginationList.appendChild(pageItem);
    }

    // Add Next button
    const nextButton = document.createElement('a');
    nextButton.classList.add('filament-tables-pagination-next');
    nextButton.textContent = 'التالي';
    if (currentPage === lastPage) {
        nextButton.classList.add('filament-tables-pagination-disabled');
        nextButton.style.pointerEvents = 'none';
    } else {
        nextButton.href = '#';
        nextButton.addEventListener('click', (e) => {
            e.preventDefault();
            paginator.gotoPage(currentPage + 1);
        });
    }
    paginationList.appendChild(nextButton);

    console.log('Custom pagination loaded with page numbers from ' + startPage + ' to ' + endPage);
});
