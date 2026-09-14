import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { HttpClient, HttpParams } from '@angular/common/http';
import { NavController } from '@ionic/angular';
import { AuthService } from '../../../services/auth.service';
import { LoadingService } from '../../../services/loading.service';
import { ApiConfigService } from '../../../services/api-config.service';

@Component({
  selector: 'app-my-requests',
  templateUrl: './my-requests.page.html',
  styleUrls: ['./my-requests.page.scss'],
  standalone: false,
})
export class MyRequestsPage implements OnInit {
  requests: any[] = [];
  isLoading = true;
  error = '';
  currentPage = 1;
  pageSize = 20;
  totalPages = 1;
  totalRequests = 0;
  filterStatus = '';

  statusOptions = [
    { value: '', label: 'Todos los estados' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'en_revision', label: 'En revisión' },
    { value: 'aprobado', label: 'Aprobado' },
    { value: 'rechazado', label: 'Rechazado' },
    { value: 'devuelto', label: 'Observado' },
    { value: 'finalizado', label: 'Finalizado' },
  ];

  constructor(
    private readonly http: HttpClient,
    public readonly authService: AuthService,
    private readonly loadingService: LoadingService,
    private readonly navCtrl: NavController,
    private readonly apiConfig: ApiConfigService,
  ) {}

  async ngOnInit() {
    await this.loadRequests();
  }

  async loadRequests(page = 1) {
    this.isLoading = true;
    this.error = '';
    this.currentPage = page;
    try {
      this.loadingService.show();
      let params = new HttpParams().set('page', page).set('limit', this.pageSize);
      if (this.filterStatus) params = params.set('status', this.filterStatus);

      const res: any = await firstValueFrom(
        this.http.get(`${this.apiConfig.getApiUrl()}/requests/assigned`, { params })
      );
      this.requests = res.requests || [];
      this.totalRequests = res.pagination?.total || 0;
      this.totalPages = res.pagination?.totalPages || 1;
    } catch (err: any) {
      this.error = err.error?.error || 'Error al cargar solicitudes';
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilter() {
    this.loadRequests(1);
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      pendiente: 'Pendiente', en_revision: 'En revisión', aprobado: 'Aprobado',
      rechazado: 'Rechazado', devuelto: 'Observado', finalizado: 'Finalizado',
      borrador: 'Borrador',
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = {
      pendiente: 'status-pending', en_revision: 'status-review', aprobado: 'status-approved',
      rechazado: 'status-rejected', devuelto: 'status-returned', finalizado: 'status-finished',
    };
    return classes[status] || '';
  }

  getPriorityClass(priority: string): string {
    return 'priority-' + (priority || 'normal');
  }
}
