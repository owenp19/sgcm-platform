import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { HttpClient, HttpParams } from '@angular/common/http';
import { ToastController } from '@ionic/angular';
import { LoadingService } from '../../../services/loading.service';
import { ApiConfigService } from '../../../services/api-config.service';

@Component({
  selector: 'app-audit',
  templateUrl: './audit.page.html',
  styleUrls: ['./audit.page.scss'],
  standalone: false,
})
export class AuditPage implements OnInit {
  logs: any[] = [];
  isLoading = true;
  error = '';
  currentPage = 1;
  pageSize = 50;
  totalPages = 1;
  totalLogs = 0;

  filterDateFrom = '';
  filterDateTo = '';
  filterAction = '';
  filterEntity = '';

  exportLoading = false;

  actionTypes = [
    { value: '', label: 'Todas las acciones' },
    { value: 'create', label: 'Creación' },
    { value: 'update', label: 'Actualización' },
    { value: 'delete', label: 'Eliminación' },
    { value: 'login', label: 'Inicio de sesión' },
    { value: 'logout', label: 'Cierre de sesión' },
    { value: 'validate', label: 'Validación' },
    { value: 'reject', label: 'Rechazo' },
    { value: 'assign', label: 'Asignación' },
    { value: 'status_change', label: 'Cambio de estado' },
  ];

  entityTypes = [
    { value: '', label: 'Todas las entidades' },
    { value: 'request', label: 'Solicitud' },
    { value: 'document', label: 'Documento' },
    { value: 'appointment', label: 'Cita' },
    { value: 'user', label: 'Usuario' },
    { value: 'role', label: 'Rol' },
    { value: 'procedure', label: 'Trámite' },
  ];

  constructor(
    private readonly http: HttpClient,
    private readonly loadingService: LoadingService,
    private readonly toastCtrl: ToastController,
    private readonly apiConfig: ApiConfigService,
  ) {}

  async ngOnInit() {
    await this.loadLogs();
  }

  async loadLogs(page = 1) {
    this.isLoading = true;
    this.error = '';
    this.currentPage = page;
    try {
      this.loadingService.show();
      let params = new HttpParams()
        .set('page', page)
        .set('limit', this.pageSize);
      if (this.filterDateFrom) params = params.set('dateFrom', this.filterDateFrom);
      if (this.filterDateTo) params = params.set('dateTo', this.filterDateTo);
      if (this.filterAction) params = params.set('action', this.filterAction);
      if (this.filterEntity) params = params.set('entityType', this.filterEntity);

      const res: any = await firstValueFrom(
        this.http.get(`${this.apiConfig.getApiUrl()}/admin/audit-log`, { params })
      );
      this.logs = res.logs || [];
      this.totalLogs = res.pagination?.total || 0;
      this.totalPages = res.pagination?.totalPages || 1;
    } catch (err: any) {
      const status = err?.status;
      if ((status === 0 || status === 429 || status === 502 || status === 503) && !this.retrying) {
        this.retrying = true;
        await this.loadLogs(page);
        return;
      }
      this.retrying = false;
      this.error = err.error?.error || (status ? `Error del servidor (${status})` : 'Error al cargar registro de auditoría');
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilters() {
    this.loadLogs(1);
  }

  clearFilters() {
    this.filterDateFrom = '';
    this.filterDateTo = '';
    this.filterAction = '';
    this.filterEntity = '';
    this.loadLogs(1);
  }

  formatDetails(details: any): string {
    if (!details) return '—';
    if (typeof details === 'string') return details;
    return JSON.stringify(details);
  }

  getActionLabel(action: string): string {
    const entry = this.actionTypes.find(a => a.value === action);
    return entry?.label || action;
  }

  getEntityLabel(entity: string): string {
    const entry = this.entityTypes.find(e => e.value === entity);
    return entry?.label || entity;
  }

  async exportExcel() {
    await this.downloadExport('excel', 'Auditoria_OCCRE.xlsx');
  }

  async exportPdf() {
    await this.downloadExport('pdf', 'Auditoria_OCCRE.pdf');
  }

  private async downloadExport(format: 'excel' | 'pdf', filename: string) {
    this.exportLoading = true;
    let params = new HttpParams();
    if (this.filterDateFrom) params = params.set('dateFrom', this.filterDateFrom);
    if (this.filterDateTo) params = params.set('dateTo', this.filterDateTo);

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
    } finally {
      this.exportLoading = false;
    }
  }

  private retrying = false;
}