import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AlertController } from '@ionic/angular';
import { AdminService } from '../admin.service';
import { LoadingService } from '../../../services/loading.service';

@Component({
  selector: 'app-appointments',
  templateUrl: './appointments.page.html',
  styleUrls: ['./appointments.page.scss'],
  standalone: false,
})
export class AppointmentsPage implements OnInit {
  appointments: any[] = [];
  isLoading = true;
  error = '';
  dateFilter = '';
  statusFilter = '';

  statusOptions = [
    { value: '', label: 'Todos los estados' },
    { value: 'pendiente', label: 'Pendientes' },
    { value: 'confirmada', label: 'Confirmadas' },
    { value: 'completada', label: 'Completadas' },
    { value: 'cancelada', label: 'Canceladas' },
  ];

  constructor(
    private readonly adminService: AdminService,
    private readonly loadingService: LoadingService,
    private readonly alertCtrl: AlertController,
  ) {}

  async ngOnInit() {
    await this.loadAppointments();
  }

  async loadAppointments() {
    this.isLoading = true;
    this.error = '';
    try {
      this.loadingService.show();
      const res: any = await firstValueFrom(this.adminService.getAllAppointments({
        date: this.dateFilter || undefined,
        status: this.statusFilter || undefined,
      }));
      this.appointments = res.appointments || [];
    } catch (err: any) {
      const status = err?.status;
      if ((status === 0 || status === 429 || status === 502 || status === 503) && !this.retrying) {
        this.retrying = true;
        await this.loadAppointments();
        return;
      }
      this.retrying = false;
      this.error = err.error?.error || (status ? `Error del servidor (${status})` : 'Error al cargar citas');
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilter() {
    this.loadAppointments();
  }

  async cancelAppointment(appt: any) {
    const alert = await this.alertCtrl.create({
      header: 'Cancelar cita',
      message: `¿Estás seguro de cancelar la cita de ${appt.citizenName}?`,
      buttons: [
        { text: 'No', role: 'cancel' },
        {
          text: 'Sí, cancelar',
          handler: async () => {
            try {
              this.loadingService.show();
              await firstValueFrom(this.adminService.cancelAppointment(appt.id));
              appt.status = 'cancelada';
            } catch (err: any) {
              const errAlert = await this.alertCtrl.create({
                header: 'Error',
                message: err.error?.error || 'Error al cancelar cita',
                buttons: ['OK'],
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
      pendiente: 'Pendiente', confirmada: 'Confirmada', completada: 'Completada', cancelada: 'Cancelada',
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = {
      pendiente: 'status-pending', confirmada: 'status-confirmed', completada: 'status-completed', cancelada: 'status-cancelled',
    };
    return classes[status] || '';
  }

  private retrying = false;
}