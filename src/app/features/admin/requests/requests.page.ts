import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AlertController } from '@ionic/angular';
import { AuthService } from '../../../services/auth.service';
import { AdminService } from '../admin.service';
import { LoadingService } from '../../../services/loading.service';

@Component({
  selector: 'app-requests',
  templateUrl: './requests.page.html',
  styleUrls: ['./requests.page.scss'],
  standalone: false,
})
export class RequestsPage implements OnInit {
  requests: any[] = [];
  filteredRequests: any[] = [];
  isLoading = true;
  error = '';
  searchTerm = '';
  statusFilter = '';
  priorityFilter = '';
  currentPage = 1;
  totalPages = 1;
  totalRequests = 0;
  funcionarios: any[] = [];
  private retrying = false;

  statusOptions = [
    { value: '', label: 'Todos los estados' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'en_revision', label: 'En revisión' },
    { value: 'devuelto', label: 'Observado' },
    { value: 'aprobado', label: 'Aprobado' },
    { value: 'rechazado', label: 'Rechazado' },
    { value: 'finalizado', label: 'Finalizado' },
  ];

  priorityOptions = [
    { value: '', label: 'Todas las prioridades' },
    { value: 'baja', label: 'Baja' },
    { value: 'normal', label: 'Normal' },
    { value: 'alta', label: 'Alta' },
    { value: 'urgente', label: 'Urgente' },
  ];

  constructor(
    private readonly adminService: AdminService,
    public readonly authService: AuthService,
    private readonly loadingService: LoadingService,
    private readonly alertCtrl: AlertController,
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
      const [reqRes, funcRes] = await Promise.all([
        firstValueFrom(this.adminService.getAllRequests({
          page, limit: 20,
          status: this.statusFilter || undefined,
          priority: this.priorityFilter || undefined,
          search: this.searchTerm || undefined,
        })),
        firstValueFrom(this.adminService.getFuncionarios()).catch(() => ({ funcionarios: [] })),
      ]);
      this.requests = (reqRes as any).requests || [];
      this.totalRequests = (reqRes as any).pagination?.total || 0;
      this.totalPages = (reqRes as any).pagination?.totalPages || 1;
      this.funcionarios = (funcRes as any).funcionarios || [];
      this.applyFilter();
    } catch (err: any) {
      const status = err?.status;
      const transient = status === 0 || status === 429 || status === 502 || status === 503;
      if (transient && !this.retrying) {
        this.retrying = true;
        await this.loadRequests(page);
        return;
      }
      this.retrying = false;
      this.error = err.error?.error
        || err.error?.message
        || (status ? `Error del servidor (${status})` : 'Error al cargar solicitudes');
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilter() {
    const term = this.searchTerm.toLowerCase();
    this.filteredRequests = this.requests.filter(r =>
      !term || r.trackingNumber?.toLowerCase().includes(term) ||
      r.citizenName?.toLowerCase().includes(term) ||
      r.procedureName?.toLowerCase().includes(term)
    );
  }

  async updateStatus(req: any) {
    const alert = await this.alertCtrl.create({
      header: 'Cambiar estado',
      message: `Selecciona el nuevo estado para la solicitud ${req.trackingNumber}`,
      inputs: [
        { type: 'radio', label: 'Pendiente', value: 'pendiente', checked: req.status === 'pendiente' },
        { type: 'radio', label: 'En revisión', value: 'en_revision', checked: req.status === 'en_revision' },
        { type: 'radio', label: 'Observado', value: 'devuelto', checked: req.status === 'devuelto' },
        { type: 'radio', label: 'Aprobado', value: 'aprobado', checked: req.status === 'aprobado' },
        { type: 'radio', label: 'Rechazado', value: 'rechazado', checked: req.status === 'rechazado' },
        { type: 'radio', label: 'Finalizado', value: 'finalizado', checked: req.status === 'finalizado' },
      ],
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Actualizar',
          handler: async (status) => {
            if (!status) return;
            try {
              this.loadingService.show();
              await firstValueFrom(this.adminService.updateRequestStatus(req.id, status));
              req.status = status;
            } catch (err: any) {
              const errAlert = await this.alertCtrl.create({
                header: 'Error', message: err.error?.error || 'Error al actualizar estado', buttons: ['OK'],
              });
              await errAlert.present();
            } finally {
              this.loadingService.hide();
            }
          },
        },
      ],
    });
    await alert.present();
  }

  async assignRequest(req: any) {
    const funcOptions = this.funcionarios.map(f => ({
      type: 'radio' as const,
      label: `${f.name} (${f.email})`,
      value: f.id.toString(),
      checked: false,
    }));
    funcOptions.unshift({ type: 'radio' as const, label: 'Sin asignar', value: '', checked: !req.assignedTo });

    const alert = await this.alertCtrl.create({
      header: 'Asignar solicitud',
      message: `Selecciona un funcionario para la solicitud ${req.trackingNumber}`,
      inputs: funcOptions,
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Asignar',
          handler: async (value) => {
            try {
              this.loadingService.show();
              const assignedTo = value ? parseInt(value) : null;
              await firstValueFrom(this.adminService.assignRequest(req.id, assignedTo));
            } catch (err: any) {
              const errAlert = await this.alertCtrl.create({
                header: 'Error', message: err.error?.error || 'Error al asignar', buttons: ['OK'],
              });
              await errAlert.present();
            } finally {
              this.loadingService.hide();
            }
          },
        },
      ],
    });
    await alert.present();
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      borrador: 'Borrador', pendiente: 'Pendiente', en_revision: 'En revisión',
      devuelto: 'Observado', aprobado: 'Aprobado', rechazado: 'Rechazado', finalizado: 'Finalizado',
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = {
      pendiente: 'status-pending', en_revision: 'status-review', aprobado: 'status-approved',
      rechazado: 'status-rejected', devuelto: 'status-returned', finalizado: 'status-finished',
      borrador: 'status-draft',
    };
    return classes[status] || '';
  }

  getPriorityLabel(p: string): string {
    const labels: Record<string, string> = { baja: 'Baja', normal: 'Normal', alta: 'Alta', urgente: 'Urgente' };
    return labels[p] || p;
  }

  getPriorityClass(p: string): string {
    const classes: Record<string, string> = { urgente: 'priority-urgent', alta: 'priority-high' };
    return classes[p] || '';
  }
}
