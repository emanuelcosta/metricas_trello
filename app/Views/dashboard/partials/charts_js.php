<script>
const labels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const scopeSeries = <?php echo json_encode($chartScopeSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const doneSeries = <?php echo json_encode($chartDoneSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const remainingSeries = <?php echo json_encode($chartRemainingSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function compressSeries(sourceLabels, sourceArrays, maxPoints) {
    if (sourceLabels.length <= maxPoints) {
        return { labels: sourceLabels, arrays: sourceArrays };
    }
    const indexes = [];
    const steps = maxPoints - 1;
    const maxIndex = sourceLabels.length - 1;
    for (let i = 0; i <= steps; i++) {
        indexes.push(Math.round((i / steps) * maxIndex));
    }
    const uniqueIndexes = [...new Set(indexes)];
    return {
        labels: uniqueIndexes.map((index) => sourceLabels[index]),
        arrays: sourceArrays.map((arr) => uniqueIndexes.map((index) => arr[index]))
    };
}

const compressed = compressSeries(labels, [scopeSeries, doneSeries, remainingSeries], 20);
const chartLabels = compressed.labels;
const chartScopeSeries = compressed.arrays[0];
const chartDoneSeries = compressed.arrays[1];
const chartRemainingSeries = compressed.arrays[2];

new Chart(document.getElementById('burnupChart'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Escopo total (demandas)',
                data: chartScopeSeries,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.65)',
                borderWidth: 1
            },
            {
                label: 'Concluídas',
                data: chartDoneSeries,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.65)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

new Chart(document.getElementById('burndownChart'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Demandas restantes',
                data: chartRemainingSeries,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,0.65)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
</script>
</body>
</html>
