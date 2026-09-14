import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { HttpClient, HttpParams } from '@angular/common/http';
import { LoadingService } from '../../../services/loading.service';
import { ApiConfigService } from '../../../services/api-config.service';

@Component({
  selector: 'app-reports',
  templateUrl: './reports.page.html',
  styleUrls: ['./reports.page.scss'],
  standalone: false,
})
export class ReportsPage implements OnInit {
  reports: any[] = [];
  isLoading = true;
  error = '';

  filterDateFrom = '';
  filterDateTo = '';
  groupBy = 'procedure';

  groupOptions = [
    { value: 'procedure', label: 'Por Trámite' },
    { value: 'status', label: 'Por Estado' },
    { value: 'funcionario', label: 'Por Funcionario' },
    { value: 'date', label: 'Por Fecha' },
  ];

  groupLabels: Record<string, string> = {
    procedure: 'Trámite',
    status: 'Estado',
    funcionario: 'Funcionario',
    date: 'Fecha',
  };

  constructor(
    private readonly http: HttpClient,
    private readonly loadingService: LoadingService,
    private readonly apiConfig: ApiConfigService,
  ) {}

  async ngOnInit() {
    await this.loadReports();
  }

  async loadReports() {
    this.isLoading = true;
    this.error = '';
    try {
      this.loadingService.show();
      let params = new HttpParams().set('groupBy', this.groupBy);
      if (this.filterDateFrom) params = params.set('dateFrom', this.filterDateFrom);
      if (this.filterDateTo) params = params.set('dateTo', this.filterDateTo);

      const res: any = await firstValueFrom(
        this.http.get(`${this.apiConfig.getApiUrl()}/admin/reports`, { params })
      );
      this.reports = res.reports || [];
    } catch (err: any) {
      this.error = err.error?.error || 'Error al cargar reportes';
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilters() {
    this.loadReports();
  }

  clearFilters() {
    this.filterDateFrom = '';
    this.filterDateTo = '';
    this.groupBy = 'procedure';
    this.loadReports();
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      pendiente: 'Pendiente', en_revision: 'En revisión', aprobado: 'Aprobado',
      rechazado: 'Rechazado', devuelto: 'Observado', finalizado: 'Finalizado',
      borrador: 'Borrador',
    };
    return labels[status] || status;
  }

  get summary(): { total: number; completed: number; success: number; avgHours: number } {
    const total = this.reports.reduce((sum: number, r: any) => sum + Number(r.count || 0), 0);
    const completed = this.reports.reduce((sum: number, r: any) => sum + Number(r.completed || 0), 0);
    const success = total ? Math.round((completed / total) * 100) : 0;

    const withTime = this.reports.filter((r: any) => r.avg_hours != null);
    const avgHours = withTime.length
      ? withTime.reduce((sum: number, r: any) => sum + Number(r.avg_hours), 0) / withTime.length
      : 0;

    return { total, completed, success, avgHours };
  }
}
