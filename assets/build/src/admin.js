/**
 * WooCommerce Review Reminder — admin bundle.
 *
 * Server-rendered PHP pages enhanced with Alpine.js components. All writes go
 * through the plugin REST API (nonce authenticated). Bundle built with esbuild.
 */
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { Chart } from 'chart.js/auto';

Alpine.plugin(focus);

const cfg = window.WRR_CONFIG || {};
const NONCE = cfg.nonce || '';

/**
 * REST base URL, re-anchored to the origin the admin page is actually served
 * from. WP's rest_url() is built from the configured siteurl (e.g. an internal
 * host:port), which the browser cannot reach when the page is viewed through a
 * preview proxy — so reuse the page's own origin while keeping the API path.
 * @returns {string}
 */
function resolveRestUrl() {
	const fallback = '/wp-json/woocommerce-review-reminder/v1/';
	try {
		const u = new URL(cfg.restUrl || fallback, window.location.origin);
		u.protocol = window.location.protocol;
		u.host = window.location.host;
		return u.toString();
	} catch (e) {
		return cfg.restUrl || fallback;
	}
}

const REST_URL = resolveRestUrl();

/**
 * Build a REST url from a relative path (e.g. 'campaigns') and query params.
 * @param {string} path
 * @param {Record<string, unknown>} [params]
 * @returns {string}
 */
function buildUrl(path, params) {
	let url = REST_URL + String(path).replace(/^\//, '');
	if (params) {
		const qs = new URLSearchParams();
		Object.entries(params).forEach(([k, v]) => {
			if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
		});
		const s = qs.toString();
		if (s) url += (url.includes('?') ? '&' : '?') + s;
	}
	return url;
}

/**
 * Fetch wrapper for the plugin REST API.
 * @param {string} method GET|POST|PUT|DELETE
 * @param {string} path
 * @param {object} [body]
 * @param {Record<string, unknown>} [params]
 * @returns {Promise<any>}
 */
async function api(method, path, body, params) {
	async function request(m) {
		const controller = new AbortController();
		const timeout = window.setTimeout(() => controller.abort(), 30000);
		const init = { method: m, headers: { 'X-WP-Nonce': NONCE }, signal: controller.signal };
		if (body !== undefined) {
			init.headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(body);
		}
		let res;
		try {
			res = await fetch(buildUrl(path, params), init);
		} catch (e) {
			window.clearTimeout(timeout);
			if (e && e.name === 'AbortError') {
				throw new Error('The request timed out. Please try again.');
			}
			throw new Error('Network error — could not reach the server.');
		}
		window.clearTimeout(timeout);
		let data = null;
		try {
			data = await res.json();
		} catch (e) {
			data = null;
		}
		return { res, data };
	}

	let result = await request(method);
	if (method === 'DELETE' && !result.res.ok && result.data && result.data.code === 'rest_no_route') {
		result = await request('POST');
	}
	if (!result.res.ok) {
		let message = 'Request failed';
		if (result.data && result.data.message) message = result.data.message;
		else if (result.data && result.data.code) message = String(result.data.code);
		throw new Error(message);
	}
	return result.data;
}

let toastWrap = null;

const TOAST_MIN_MS = 3000;

/**
 * Lightweight toast notification. Stays visible for at least TOAST_MIN_MS.
 * @param {string} message
 * @param {'success'|'error'} [type]
 * @param {() => void} [onDismiss] Invoked once the toast has been dismissed.
 */
function toast(message, type, onDismiss) {
	if (!toastWrap) {
		toastWrap = document.createElement('div');
		toastWrap.className = 'wrr-toast-wrap';
		document.body.appendChild(toastWrap);
	}
	const el = document.createElement('div');
	el.className = 'wrr-toast' + (type === 'error' ? ' wrr-toast-error' : '');
	el.textContent = message;
	toastWrap.appendChild(el);
	window.setTimeout(() => {
		el.remove();
		if (typeof onDismiss === 'function') onDismiss();
	}, TOAST_MIN_MS);
}

function pad(n) {
	return String(n).padStart(2, '0');
}

/**
 * Date range helper: {from, to} ISO dates for the last N days.
 * @param {number} days
 * @returns {{from: string, to: string}}
 */
function dateRange(days) {
	const now = new Date();
	const from = new Date(now.getTime() - (days - 1) * 86400000);
	return {
		from: `${from.getFullYear()}-${pad(from.getMonth() + 1)}-${pad(from.getDate())}`,
		to: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
	};
}

function formatNumber(n) {
	if (n === undefined || n === null) return '0';
	return new Intl.NumberFormat().format(Number(n));
}

function formatRate(n) {
	if (n === undefined || n === null || Number.isNaN(Number(n))) return '0%';
	return `${Number(n)}%`;
}

/**
 * Render a Chart.js bar chart onto a canvas (destroys any prior chart).
 * @param {HTMLCanvasElement} canvas
 * @param {string[]} labels
 * @param {number[]} values
 * @param {object} [opts]
 * @returns {Chart}
 */
function renderChart(canvas, labels, values, opts) {
	if (canvas._wrrChart) canvas._wrrChart.destroy();
	canvas._wrrChart = new Chart(canvas, {
		type: 'bar',
		data: {
			labels,
			datasets: [
				{
					label: (opts && opts.label) || 'Events',
					data: values,
					backgroundColor: (opts && opts.color) || '#4f46e5',
					hoverBackgroundColor: (opts && opts.hoverColor) || '#4338ca',
					borderRadius: 4,
					maxBarThickness: 28,
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { display: false },
				tooltip: {
					callbacks: {
						label: (ctx) => ` ${formatNumber(ctx.parsed.y)} events`,
					},
				},
			},
			scales: {
				x: {
					grid: { display: false },
					ticks: { color: '#6b7280', font: { size: 11 } },
				},
				y: {
					beginAtZero: true,
					ticks: {
						precision: 0,
						color: '#6b7280',
						font: { size: 11 },
						callback: (v) => formatNumber(v),
					},
					grid: { color: '#f3f4f6' },
				},
			},
		},
	});
	return canvas._wrrChart;
}

function commaListToArray(value) {
	return String(value || '')
		.split(',')
		.map((s) => s.trim())
		.filter((s) => /^\d+$/.test(s))
		.map(Number);
}

function arrayToCommaList(arr) {
	return (Array.isArray(arr) ? arr : []).join(', ');
}

window.WRR = {
	api,
	toast,
	dateRange,
	renderChart,
	formatNumber,
	formatRate,
	commaListToArray,
	arrayToCommaList,
	reload() {
		window.location.reload();
	},
	/**
	 * Navigate to a plugin admin page (supports query strings).
	 * @param {string} pageSlug
	 * @param {object} [params]
	 */
	nav(pageSlug, params) {
		const base = (cfg.adminUrl || 'admin.php') + '?page=' + pageSlug;
		const qs = new URLSearchParams();
		Object.entries(params || {}).forEach(([k, v]) => {
			if (v !== undefined && v !== null) qs.set(k, String(v));
		});
		const s = qs.toString();
		window.location.href = s ? base + '&' + s : base;
	},
};

Chart.defaults.font.family =
	'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
Chart.defaults.color = '#6b7280';

document.addEventListener('alpine:init', () => {
	/* Confirm dialog used by list/detail pages. */
	Alpine.data('wrrConfirm', () => ({
		state: null,
		busy: false,
		ask(payload) {
			this.state = payload;
		},
		async confirm() {
			if (!this.state || !this.state.action) return;
			this.busy = true;
			try {
				await this.state.action();
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.busy = false;
				this.state = null;
			}
		},
		cancel() {
			this.state = null;
		},
	}));

	/* Campaign list row actions (activate/pause/duplicate/delete). */
	Alpine.data('wrrCampaigns', () => ({
		busy: false,
		async runAction(id, actionName) {
			this.busy = true;
			try {
				await WRR.api('POST', `campaigns/${id}/${actionName}`);
				WRR.toast(`Campaign ${actionName === 'duplicate' ? 'duplicated' : actionName + 'd'}.`, 'success', () => WRR.reload());
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.busy = false;
			}
		},
		askDelete(id, name) {
			this.$dispatch('wrr-confirm-ask', {
				title: 'Delete campaign?',
				message: `This permanently deletes “${name}” and its review requests. This cannot be undone.`,
				confirmLabel: 'Delete',
				action: () => this.deleteCampaign(id),
			});
		},
		async deleteCampaign(id) {
			await WRR.api('DELETE', `campaigns/${id}`);
			const row = this.$root.querySelector(`tr[data-cid="${id}"]`);
			if (row && row.parentNode) {
				row.parentNode.removeChild(row);
			}
			WRR.toast('Campaign deleted.', 'success', () => WRR.reload());
		},
	}));

	/* Requests list + detail cancel actions. */
	Alpine.data('wrrRequests', () => ({
		askCancel(id) {
			this.$dispatch('wrr-confirm-ask', {
				title: 'Cancel this request?',
				message: 'The request will be cancelled and no email will be sent.',
				confirmLabel: 'Cancel request',
				action: () => this.cancelRequest(id),
			});
		},
		async cancelRequest(id) {
			await WRR.api('POST', `requests/${id}`);
			WRR.toast('Request cancelled.', 'success', () => WRR.reload());
		},
	}));

	/* Sidebar collapse rail. */
	Alpine.data('wrrRail', () => ({
		collapsed: localStorage.getItem('wrr_rail_collapsed') === '1',
		init() {
			document.body.classList.toggle('wrr-rail-collapsed', this.collapsed);
		},
		toggle() {
			this.collapsed = !this.collapsed;
			document.body.classList.toggle('wrr-rail-collapsed', this.collapsed);
			localStorage.setItem('wrr_rail_collapsed', this.collapsed ? '1' : '0');
		},
	}));

	/* Email template list: preview / create / edit / delete / test email. */
	Alpine.data('wrrTemplates', (initial) => ({
		items: Array.isArray(initial) ? initial : (initial && initial.items) || [],
		preview: null,
		previewHtml: null,
		previewLoading: false,
		form: { id: 0, name: '', description: '', subject: '', body: '' },
		formOpen: false,
		saving: false,
		deletingId: null,
		testOpen: false,
		testTo: (cfg && cfg.userEmail) || '',
		testName: '',
		testSubject: '',
		testBody: '',
		sendingTest: false,
		openPreview(t) {
			this.preview = t;
			this.previewHtml = null;
			this.previewLoading = true;
			WRR.api('POST', 'emails/preview', {
				subject: (t && t.subject) || '',
				body: (t && t.body) || '',
			})
				.then((res) => {
					this.previewHtml = res && res.body ? { subject: res.subject || '', body: res.body } : null;
				})
				.catch((err) => {
					WRR.toast(err && err.message ? err.message : 'Could not render the preview.', 'error');
				})
				.finally(() => {
					this.previewLoading = false;
				});
		},
		closePreview() {
			this.preview = null;
			this.previewHtml = null;
			this.previewLoading = false;
		},
		openEdit(t) {
			this.form = t
				? {
						id: t.id,
						name: t.name || '',
						description: t.description || '',
						subject: t.subject || '',
						body: t.body || '',
				  }
				: { id: 0, name: '', description: '', subject: '', body: '' };
			this.formOpen = true;
		},
		closeForm() {
			this.formOpen = false;
		},
		async saveTemplate() {
			const form = this.form || { id: 0, name: '', subject: '', body: '' };
			if (!form.name || !String(form.name).trim()) {
				WRR.toast('Email template name is required.', 'error');
				return;
			}
			this.saving = true;
			try {
				const payload = {
					name: String(form.name).trim(),
					description: form.description || '',
					subject: form.subject || '',
					body: form.body || '',
				};
				if (form.id > 0) {
					await WRR.api('PUT', `templates/${form.id}`, payload);
					WRR.toast('Email template updated.', 'success', () => WRR.reload());
				} else {
					await WRR.api('POST', 'templates', payload);
					WRR.toast('Email template created.', 'success', () => WRR.reload());
				}
			} catch (err) {
				WRR.toast(err && err.message ? err.message : 'Could not save the email template.', 'error');
			} finally {
				this.saving = false;
			}
		},
		askDelete(id, name) {
			this.$dispatch('wrr-confirm-ask', {
				title: 'Delete email template?',
				message: `This permanently deletes “${name}”.`,
				confirmLabel: 'Delete',
				action: () => this.deleteTemplate(id),
			});
		},
		async deleteTemplate(id) {
			await WRR.api('DELETE', `templates/${id}`);
			WRR.toast('Email template deleted.', 'success', () => WRR.reload());
		},
		openTest(t) {
			this.testTo = (cfg && cfg.userEmail) || '';
			this.testName = (t && t.name) || '';
			this.testSubject = (t && t.subject) || '';
			this.testBody = (t && t.body) || '';
			this.testOpen = true;
		},
		openTestFromForm() {
			const form = this.form || {};
			this.testTo = (cfg && cfg.userEmail) || '';
			this.testName = form.name || '';
			this.testSubject = form.subject || '';
			this.testBody = form.body || '';
			this.testOpen = true;
		},
		closeTest() {
			this.testOpen = false;
		},
		async sendTest() {
			if (!this.testTo || !String(this.testTo).trim()) {
				WRR.toast('Please enter a recipient email.', 'error');
				return;
			}
			this.sendingTest = true;
			try {
				const res = await WRR.api('POST', 'emails/test', {
					to: this.testTo.trim(),
					subject: this.testSubject,
					body: this.testBody,
				});
				WRR.toast((res && res.message) || 'Test email sent.');
			} catch (err) {
				WRR.toast((err && err.message) || 'Could not send the test email.', 'error');
			} finally {
				this.sendingTest = false;
			}
		},
	}));

	/* Settings page: grouped settings + suppression list + test email. */
	Alpine.data('wrrSettings', (initial) => ({
		settings: initial || {},
		saving: false,
		tab: sessionStorage.getItem('wrr_settings_tab') || 'general',
		suppressions: [],
		suppressionsLoading: false,
		newSuppression: '',
		testTo: '',
		testSubject: '',
		testBody: '',
		sendingTest: false,
		setTab(tab) {
			this.tab = tab;
			sessionStorage.setItem('wrr_settings_tab', tab);
		},
		async saveSettings() {
			this.saving = true;
			try {
				const res = await WRR.api('POST', 'settings', { settings: this.settings });
				if (res && res.settings) this.settings = res.settings;
				WRR.toast('Settings saved.');
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.saving = false;
			}
		},
		async loadSuppressions() {
			this.suppressionsLoading = true;
			try {
				const res = await WRR.api('GET', 'suppressions', null, { per_page: 100, page: 1 });
				this.suppressions = (res && res.items) || [];
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.suppressionsLoading = false;
			}
		},
		async addSuppression() {
			const email = this.newSuppression.trim();
			if (!email) return;
			try {
				await WRR.api('POST', 'suppressions', { email });
				WRR.toast('Email suppressed.');
				this.newSuppression = '';
				this.loadSuppressions();
			} catch (err) {
				WRR.toast(err.message, 'error');
			}
		},
		async removeSuppression(email) {
			try {
				await WRR.api('DELETE', `suppressions/${encodeURIComponent(email)}`);
				WRR.toast('Suppression removed.');
				this.loadSuppressions();
			} catch (err) {
				WRR.toast(err.message, 'error');
			}
		},
		async sendTest() {
			if (!this.testTo.trim()) {
				WRR.toast('Please enter a recipient email.', 'error');
				return;
			}
			this.sendingTest = true;
			try {
				const res = await WRR.api('POST', 'emails/test', {
					to: this.testTo.trim(),
					subject: this.testSubject,
					body: this.testBody,
				});
				WRR.toast((res && res.message) || 'Test email sent.');
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.sendingTest = false;
			}
		},
	}));

	/* Analytics: metric + range switcher backed by Chart.js. */
	Alpine.data('wrrAnalytics', (initial) => ({
		metric: initial.metric || 'sent',
		range: initial.range || '30',
		loading: false,
		init() {
			this.fetchSeries();
		},
		setMetric(m) {
			if (m === this.metric) return;
			this.metric = m;
			this.fetchSeries();
		},
		setRange(r) {
			if (r === this.range) return;
			this.range = r;
			this.fetchSeries();
		},
		async fetchSeries() {
			this.loading = true;
			try {
				const range = WRR.dateRange(Number(this.range));
				const res = await WRR.api('GET', 'analytics/time-series', null, {
					metric: this.metric,
					from: range.from,
					to: range.to,
				});
				const series = (res && res.series) || [];
				const canvas = this.$refs.chart;
				if (canvas) {
					WRR.renderChart(
						canvas,
						series.map((s) => s.date),
						series.map((s) => Number(s.count)),
						{ label: this.metric }
					);
				}
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.loading = false;
			}
		},
	}));

	/* Campaign editor: full config form. */
	Alpine.data('wrrCampaignEditor', (initial) => ({
		id: initial.id || 0,
		name: initial.name || '',
		description: initial.description || '',
		config: initial.config || {},
		saving: false,
		get productIdsText() {
			return WRR.arrayToCommaList(this.config.products && this.config.products.product_ids);
		},
		set productIdsText(v) {
			this.config.products.product_ids = WRR.commaListToArray(v);
		},
		get categoryIdsText() {
			return WRR.arrayToCommaList(this.config.products && this.config.products.category_ids);
		},
		set categoryIdsText(v) {
			this.config.products.category_ids = WRR.commaListToArray(v);
		},
		get tagIdsText() {
			return WRR.arrayToCommaList(this.config.products && this.config.products.tag_ids);
		},
		set tagIdsText(v) {
			this.config.products.tag_ids = WRR.commaListToArray(v);
		},
		get excludeProductIdsText() {
			return WRR.arrayToCommaList(this.config.products && this.config.products.exclude_product_ids);
		},
		set excludeProductIdsText(v) {
			this.config.products.exclude_product_ids = WRR.commaListToArray(v);
		},
		get excludeCategoryIdsText() {
			return WRR.arrayToCommaList(this.config.products && this.config.products.exclude_category_ids);
		},
		set excludeCategoryIdsText(v) {
			this.config.products.exclude_category_ids = WRR.commaListToArray(v);
		},
		group(name, patch) {
			this.config[name] = Object.assign({}, this.config[name] || {}, patch);
		},
		async save(publish) {
			if (!this.name.trim()) {
				WRR.toast('Campaign name is required.', 'error');
				return;
			}
			this.saving = true;
			try {
				const payload = {
					name: this.name.trim(),
					description: this.description,
					config: this.config,
				};
				if (publish) payload.status = 'active';
				if (this.id > 0) {
					await WRR.api('PUT', `campaigns/${this.id}`, payload);
					WRR.nav('wrr-campaigns', { wrr_saved: publish ? 'activated' : 'updated' });
				} else {
					const res = await WRR.api('POST', 'campaigns', payload);
					WRR.nav('wrr-campaigns', {
						wrr_saved: publish ? 'created' : 'draft',
						wrr_id: res && res.id ? res.id : undefined,
					});
				}
			} catch (err) {
				WRR.toast(err.message, 'error');
			} finally {
				this.saving = false;
			}
		},
	}));
});

Alpine.start();
