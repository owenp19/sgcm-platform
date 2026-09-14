import { NativeCapabilitiesService } from '../../../services/native-capabilities.service';
import { Component, OnInit, OnDestroy, ViewChildren, QueryList, ElementRef, AfterViewInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { HttpClient, HttpParams } from '@angular/common/http';
import { NavController, ToastController } from '@ionic/angular';
import { Chart, registerables } from 'chart.js';
import { AuthService } from '../../../services/auth.service';
import { AdminStatsService, DashboardData } from '../../../services/admin-stats.service';
import { ApiConfigService } from '../../../services/api-config.service';
import { addIcons } from 'ionicons';
import {
  clipboardOutline,
  documentTextOutline,
  calendarOutline,
  peopleOutline,
  documentOutline,
  printOutline,
  refreshOutline,
  alertCircleOutline,
  timeOutline,
  flagOutline,
  checkmarkCircleOutline,
  closeCircleOutline,
  informationCircleOutline,
  ellipseOutline,
  trendingUpOutline,
  trendingDownOutline,
  removeOutline,
  optionsOutline,
  navigateOutline,
} from 'ionicons/icons';

Chart.register(...registerables);

interface ChartPalette {
  primary: string;
  success: string;
  warning: string;
  danger: string;
  info: string;
  teal: string;
  pink: string;
  purple: string;
  orange: string;
  gray: string;
}

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.page.html',
  styleUrls: ['./dashboard.page.scss'],
  standalone: false,
})
export class DashboardPage implements OnInit, OnDestroy, AfterViewInit {
  @ViewChildren('chartCanvas') chartCanvases!: QueryList<ElementRef<HTMLCanvasElement>>;

  stats: DashboardData | null = null;
  isLoading = true;
  error = '';
  currentUser: any = null;
  lastUpdate = '';
  filterDateFrom = '';
  filterDateTo = '';
  filterStatus = '';

  readonly todayLabel = new Date().toLocaleDateString('es-CO', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  private charts: Chart[] = [];

  constructor(
    private readonly adminStatsService: AdminStatsService,
    private readonly authService: AuthService,
    private readonly navCtrl: NavController,
    private readonly http: HttpClient,
    private readonly toastCtrl: ToastController,
    private readonly apiConfig: ApiConfigService,
    private readonly nativeCap: NativeCapabilitiesService,
  ) {
    addIcons({
      clipboardOutline,
      documentTextOutline,
      calendarOutline,
      peopleOutline,
      documentOutline,
      printOutline,
      refreshOutline,
      alertCircleOutline,
      timeOutline,
      flagOutline,
      checkmarkCircleOutline,
      closeCircleOutline,
      informationCircleOutline,
      ellipseOutline,
      trendingUpOutline,
      trendingDownOutline,
      removeOutline,
      optionsOutline,
      navigateOutline,
    });
  }

  async ngOnInit() {
    this.currentUser = this.authService.getUser();
    await this.loadStats();
  }

  ngAfterViewInit() {
    this.chartCanvases.changes.subscribe(() => this.renderCharts());
    setTimeout(() => this.renderCharts(), 500);
  }

  ngOnDestroy() {
    this.destroyCharts();
  }

  get firstName(): string {
    return this.currentUser?.firstName || 'Funcionario';
  }

  get greeting(): string {
    const h = new Date().getHours();
    if (h < 12) return 'Buenos días';
    if (h < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

  async loadStats() {
    this.isLoading = true;
    this.error = '';
    try {
      const res = await firstValueFrom(this.adminStatsService.getDashboard(
        this.filterDateFrom || undefined,
        this.filterDateTo || undefined,
        this.filterStatus || undefined,
      ));
      this.stats = res;
      this.lastUpdate = res.lastUpdate;
      setTimeout(() => this.renderCharts(), 300);
    } catch (err: any) {
      this.error = err.error?.error || 'Error al cargar estadísticas';
    } finally {
      this.isLoading = false;
    }
  }

  async applyFilters() {
    await this.loadStats();
  }

  clearFilters() {
    this.filterDateFrom = '';
    this.filterDateTo = '';
    this.filterStatus = '';
    this.loadStats();
  }

  safeVal(val: any, fallback = 0): number {
    return (val ?? fallback);
  }

  private destroyCharts() {
    this.charts.forEach(c => c.destroy());
    this.charts = [];
  }

  /** Lee una variable CSS del tema global (colores adaptativos light/dark). */
  private cssVar(name: string, fallback = ''): string {
    const val = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return val || fallback;
  }

  private palette(): ChartPalette {
    return {
      primary: this.cssVar('--app-primary', '#1d72b5'),
      success: this.cssVar('--app-success', '#16a34a'),
      warning: this.cssVar('--app-warning', '#d97706'),
      danger: this.cssVar('--app-danger', '#dc2626'),
      info: this.cssVar('--app-info', '#4f46e5'),
      teal: '#14b8a6',
      pink: '#ec4899',
      purple: '#8b5cf6',
      orange: '#f97316',
      gray: this.cssVar('--app-text-soft', '#6b7280'),
    };
  }

  private renderCharts() {
    if (!this.stats) return;
    this.destroyCharts();

    const canvases = this.chartCanvases?.toArray();
    if (!canvases?.length) return;

    const colors = this.palette();
    const chartColors = [
      colors.primary, colors.success, colors.warning, colors.danger,
      colors.purple, colors.pink, colors.orange, colors.teal, colors.info,
    ];

    for (const canvasRef of canvases) {
      const canvas = canvasRef.nativeElement;
      const ctx = canvas.getContext('2d');
      if (!ctx) continue;

      const chartId = canvas.id;
      if (!chartId) continue;

      if (chartId === 'chart-docs-status') {
        if (!this.stats.documentsByStatus?.length) continue;
        new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: this.stats.documentsByStatus.map(d => d.label),
            datasets: [{
              data: this.stats.documentsByStatus.map(d => d.count),
              backgroundColor: [colors.warning, colors.success],
              borderWidth: 0,
              hoverOffset: 6,
            }],
          },
          options: this.chartOptions('doughnut', 'Documentos por estado'),
        });
      }

      if (chartId === 'chart-docs-month') {
        if (!this.stats.documentsByMonth?.length) continue;
        const months = this.stats.documentsByMonth.map(d => {
          const [y, m] = d.month.split('-');
          const names = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
          return `${names[parseInt(m)-1]} ${y}`;
        });
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: months,
            datasets: [
              { label: 'Validados', data: this.stats.documentsByMonth.map(d => d.validados), backgroundColor: colors.success, borderRadius: 6, maxBarThickness: 28 },
              { label: 'Pendientes', data: this.stats.documentsByMonth.map(d => d.pendientes), backgroundColor: colors.warning, borderRadius: 6, maxBarThickness: 28 },
            ],
          },
          options: this.chartOptions('bar', 'Documentos cargados por mes', true),
        });
      }

      if (chartId === 'chart-requests-status') {
        if (!this.stats.requestsByStatus?.length) continue;
        const filtered = this.stats.requestsByStatus.filter(s => s.count > 0);
        if (!filtered.length) continue;
        new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: filtered.map(d => d.label),
            datasets: [{
              data: filtered.map(d => d.count),
              backgroundColor: chartColors.slice(0, filtered.length),
              borderWidth: 0,
              hoverOffset: 6,
            }],
          },
          options: this.chartOptions('doughnut', 'Solicitudes por estado'),
        });
      }

      if (chartId === 'chart-users-month') {
        if (!this.stats.usersByMonth?.length) continue;
        const months = this.stats.usersByMonth.map(d => {
          const [y, m] = d.month.split('-');
          const names = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
          return `${names[parseInt(m)-1]} ${y}`;
        });
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: months,
            datasets: [
              { label: 'Ciudadanos', data: this.stats.usersByMonth.map(d => d.ciudadanos), backgroundColor: colors.primary, borderRadius: 6, maxBarThickness: 28 },
              { label: 'Funcionarios', data: this.stats.usersByMonth.map(d => d.funcionarios), backgroundColor: colors.teal, borderRadius: 6, maxBarThickness: 28 },
            ],
          },
          options: this.chartOptions('bar', 'Usuarios registrados por mes', true),
        });
      }

      if (chartId === 'chart-appointments') {
        if (!this.stats.appointmentsByDay?.length) continue;
        const days = this.stats.appointmentsByDay.map(d => {
          const date = new Date(d.date + 'T00:00:00');
          return date.toLocaleDateString('es', { day: 'numeric', month: 'short' });
        });
        new Chart(ctx, {
          type: 'line',
          data: {
            labels: days,
            datasets: [
              { label: 'Ocupados', data: this.stats.appointmentsByDay.map(d => d.ocupados), borderColor: colors.orange, backgroundColor: colors.orange + '22', fill: true, tension: 0.4, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 },
              { label: 'Completados', data: this.stats.appointmentsByDay.map(d => d.completados), borderColor: colors.success, backgroundColor: colors.success + '22', fill: true, tension: 0.4, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 },
            ],
          },
          options: this.chartOptions('line', 'Citas por día (últimos 30 días)', true),
        });
      }

      if (chartId === 'chart-requests-month') {
        if (!this.stats.monthlyStats?.length) continue;
        const months = this.stats.monthlyStats.map(d => {
          const [y, m] = d.month.split('-');
          const names = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
          return `${names[parseInt(m)-1]} ${y}`;
        });
        new Chart(ctx, {
          type: 'line',
          data: {
            labels: months,
            datasets: [{
              label: 'Solicitudes',
              data: this.stats.monthlyStats.map(d => d.total),
              borderColor: colors.info,
              backgroundColor: colors.info + '22',
              fill: true,
              tension: 0.4,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: colors.info,
              borderWidth: 2,
            }],
          },
          options: this.chartOptions('line', 'Solicitudes por mes', true),
        });
      }

      if (chartId === 'chart-top-procedures') {
        if (!this.stats.topProcedures?.length) continue;
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: this.stats.topProcedures.map(d => d.name.length > 20 ? d.name.substring(0, 20) + '...' : d.name),
            datasets: [{
              label: 'Cantidad',
              data: this.stats.topProcedures.map(d => d.count),
              backgroundColor: this.stats.topProcedures.map((_, i) => chartColors[i % chartColors.length]),
              borderRadius: 6,
              maxBarThickness: 22,
            }],
          },
          options: {
            ...this.chartOptions('bar', 'Trámites más solicitados'),
            indexAxis: 'y',
          },
        });
      }
    }
  }

  private chartOptions(type: string, title: string, stacked = false): any {
    const isDoughnut = type === 'doughnut';
    const text = this.cssVar('--app-text', '#102033');
    const gridColor = this.cssVar('--app-border', '#e5e7eb');
    return {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: !isDoughnut,
          position: 'bottom' as const,
          labels: { padding: 16, usePointStyle: true, boxWidth: 8, color: text, font: { size: 11, family: 'Roboto, sans-serif' } },
        },
        tooltip: {
          backgroundColor: '#1e293b',
          titleFont: { size: 12 },
          bodyFont: { size: 12 },
          padding: 12,
          cornerRadius: 10,
          displayColors: true,
        },
      },
      scales: isDoughnut ? undefined : {
        x: { grid: { display: false }, ticks: { color: text, font: { size: 10 } }, stacked },
        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: text, font: { size: 10 }, stepSize: 1 }, stacked },
      },
      cutout: isDoughnut ? '68%' : undefined,
    };
  }

  onCardClick(link: string) {
    if (!link) return;
    this.navigateToByPath(link);
  }

  private navigateToByPath(path: string) {
    const map: Record<string, string> = {
      '/admin/requests': 'solicitudes',
      '/admin/documents': 'documentos',
      '/admin/users': 'usuarios',
      '/admin/appointments': 'citas',
      '/admin/audit': 'auditoria',
    };
    const section = map[path];
    if (section) this.navigateTo(section);
  }

  navigateTo(section: string) {
    switch (section) {
      case 'inicio': this.navCtrl.navigateForward('/admin/dashboard'); break;
      case 'reportes': this.navCtrl.navigateForward('/admin/reports'); break;
      case 'usuarios': this.navCtrl.navigateForward('/admin/users'); break;
      case 'documentos': this.navCtrl.navigateForward('/admin/documents'); break;
      case 'solicitudes': this.navCtrl.navigateForward('/admin/requests'); break;
      case 'citas': this.navCtrl.navigateForward('/admin/appointments'); break;
      case 'auditoria': this.navCtrl.navigateForward('/admin/audit'); break;
      case 'perfil': this.navCtrl.navigateForward('/admin/profile'); break;
      default: break;
    }
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      pendiente: 'Pendiente', en_revision: 'En revisión', aprobado: 'Aprobado',
      rechazado: 'Rechazado', devuelto: 'Observado', finalizado: 'Finalizado',
      borrador: 'Borrador', confirmada: 'Confirmada', cancelada: 'Cancelada',
      completada: 'Completada',
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = {
      pendiente: 'status-pending', en_revision: 'status-review', aprobado: 'status-approved',
      rechazado: 'status-rejected', devuelto: 'status-returned', finalizado: 'status-finished',
      confirmada: 'status-approved', cancelada: 'status-rejected', completada: 'status-finished',
    };
    return classes[status] || '';
  }

  getActivityIcon(type: string): string {
    const icons: Record<string, string> = {
      request: 'clipboard-outline', document: 'document-text-outline', appointment: 'calendar-outline',
    };
    return icons[type] || 'ellipse-outline';
  }

  getAlertIcon(type: string): string {
    const icons: Record<string, string> = {
      warning: 'alert-circle-outline', danger: 'close-circle-outline',
      info: 'information-circle-outline', success: 'checkmark-circle-outline',
    };
    return icons[type] || 'ellipse-outline';
  }

  refresh() {
    this.loadStats();
  }

  async exportExcel() {
    await this.downloadExport('excel', 'Reporte_OCCRE.xlsx');
  }

  async exportPdf() {
    await this.downloadExport('pdf', 'Reporte_OCCRE.pdf');
  }

  private async downloadExport(format: 'excel' | 'pdf', filename: string) {
    let params = new HttpParams();
    if (this.filterDateFrom) params = params.set('dateFrom', this.filterDateFrom);
    if (this.filterDateTo) params = params.set('dateTo', this.filterDateTo);
    if (this.filterStatus) params = params.set('status', this.filterStatus);

    try {
      const blob: Blob = await firstValueFrom(
        this.http.get(`${this.apiConfig.getApiUrl()}/admin/export/${format}`, {
          params,
          responseType: 'blob',
        })
      );
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch {
      const toast = await this.toastCtrl.create({
        message: 'Error al exportar. Intente de nuevo.',
        duration: 3000,
        color: 'danger',
        position: 'bottom',
      });
      await toast.present();
    }
  }
  /* ══ Botonera nativa (funcionario de ventanilla) ══ */
  private async toast(msg: string, color: 'success' | 'warning' | 'danger' | 'medium' = 'medium') {
    const t = await this.toastCtrl.create({ message: msg, duration: 3200, position: 'bottom', color });
    await t.present();
  }

  async capturePosition(): Promise<void> {
    const g = await this.nativeCap.getCurrentPosition();
    if (g) {
      void this.toast('GPS: ' + g.latitude.toFixed(5) + ', ' + g.longitude.toFixed(5), 'success');
    }
  }

}