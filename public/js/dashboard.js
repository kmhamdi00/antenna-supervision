(function () {
  "use strict";

  const state = { city: "", status: "", page: 1, limit: 20 };

  const tbody = document.getElementById("antennas-body");
  const pagination = document.getElementById("pagination");
  const alertZone = document.getElementById("alert-zone");

  function showAlert(message, type) {
    alertZone.innerHTML =
      '<div class="alert alert-' +
      type +
      ' alert-dismissible fade show" role="alert">' +
      message +
      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function statusBadge(status) {
    const cls = status === "UP" ? "success" : "danger";
    return '<span class="badge bg-' + cls + '">' + status + "</span>";
  }

  function formatLastIntervention(last) {
    if (!last) {
      return '<span class="text-muted">Aucune</span>';
    }
    const closed = last.ended_at ? " (clôturée)" : " (active)";
    return (
      "<strong>" +
      last.priority +
      "</strong> — " +
      last.technician_identity +
      closed +
      '<br><small class="text-muted">' +
      last.description +
      "</small>"
    );
  }

  async function fetchAntennas() {
    tbody.innerHTML =
      '<tr><td colspan="6" class="text-center text-muted">Chargement...</td></tr>';

    const params = new URLSearchParams();
    if (state.city) params.set("city", state.city);
    if (state.status) params.set("status", state.status);
    params.set("page", state.page);
    params.set("limit", state.limit);

    try {
      const response = await fetch("/api/antennas?" + params.toString());
      const payload = await response.json();

      if (!response.ok) {
        showAlert(
          "Erreur lors du chargement : " +
            (payload.error?.message || response.status),
          "danger",
        );
        tbody.innerHTML = "";
        return;
      }

      renderRows(payload.data);
      renderPagination(payload.meta);
    } catch (e) {
      showAlert("Erreur réseau : impossible de contacter l'API.", "danger");
    }
  }

  function renderRows(antennas) {
    if (antennas.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center text-muted">Aucune antenne trouvée.</td></tr>';
      return;
    }

    tbody.innerHTML = antennas
      .map(function (antenna) {
        const last = antenna.last_intervention;
        const canClose = last && !last.ended_at;
        const closeButton = canClose
          ? '<button class="btn btn-sm btn-outline-success" data-close-id="' +
            last.id +
            '">Clôturer</button>'
          : "";

        return (
          "<tr>" +
          "<td>" +
          antenna.id +
          "</td>" +
          "<td>" +
          escapeHtml(antenna.name) +
          "</td>" +
          "<td>" +
          escapeHtml(antenna.city) +
          "</td>" +
          "<td>" +
          statusBadge(antenna.status) +
          "</td>" +
          "<td>" +
          formatLastIntervention(last) +
          "</td>" +
          "<td>" +
          closeButton +
          "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function renderPagination(meta) {
    const pages = Math.max(1, Math.ceil(meta.total / meta.limit));
    let html = "";
    for (let p = 1; p <= pages; p++) {
      html +=
        '<li class="page-item' +
        (p === meta.page ? " active" : "") +
        '">' +
        '<a class="page-link" href="#" data-page="' +
        p +
        '">' +
        p +
        "</a></li>";
    }
    pagination.innerHTML = html;
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  document.getElementById("filters").addEventListener("submit", function (e) {
    e.preventDefault();
    state.city = document.getElementById("filter-city").value.trim();
    state.status = document.getElementById("filter-status").value;
    state.page = 1;
    fetchAntennas();
  });

  pagination.addEventListener("click", function (e) {
    if (e.target.dataset.page) {
      e.preventDefault();
      state.page = parseInt(e.target.dataset.page, 10);
      fetchAntennas();
    }
  });

  tbody.addEventListener("click", async function (e) {
    const id = e.target.dataset.closeId;
    if (!id) return;

    e.target.disabled = true;
    try {
      const response = await fetch("/api/interventions/" + id + "/close", {
        method: "PATCH",
        headers: { "X-API-KEY": window.__API_KEY__ },
      });
      const payload = await response.json();

      if (!response.ok) {
        showAlert(
          "Erreur : " + (payload.error?.message || response.status),
          "danger",
        );
        e.target.disabled = false;
        return;
      }

      showAlert("Intervention #" + id + " clôturée.", "success");
      fetchAntennas();
    } catch (err) {
      showAlert("Erreur réseau lors de la clôture.", "danger");
      e.target.disabled = false;
    }
  });

  fetchAntennas();
})();
