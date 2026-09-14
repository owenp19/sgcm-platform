import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AlertController, ToastController } from '@ionic/angular';
import { AuthService } from '../../../services/auth.service';
import { AdminService } from '../admin.service';
import { LoadingService } from '../../../services/loading.service';

@Component({
  selector: 'app-users',
  templateUrl: './users.page.html',
  styleUrls: ['./users.page.scss'],
  standalone: false,
})
export class UsersPage implements OnInit {
  users: any[] = [];
  filteredUsers: any[] = [];
  isLoading = true;
  error = '';
  searchTerm = '';
  totalUsers = 0;
  currentPage = 1;
  pageSize = 20;
  totalPages = 1;
  currentUser: any = null;
  isRoleModalOpen = false;
  editRolesUser: any = null;
  selectedRoles: string[] = [];
  availableRoles = ['admin', 'funcionario', 'ciudadano'];
  roleLabels: Record<string, string> = {
    admin: 'Administrador',
    funcionario: 'Funcionario',
    ciudadano: 'Ciudadano',
  };
  roleDescriptions: Record<string, string> = {
    admin: 'Acceso total al sistema',
    funcionario: 'Gestión de trámites y documentos',
    ciudadano: 'Solicitud de trámites y citas',
  };

  constructor(
    private readonly adminService: AdminService,
    public readonly authService: AuthService,
    private readonly loadingService: LoadingService,
    private readonly alertCtrl: AlertController,
    private readonly toastCtrl: ToastController,
  ) {}

  async ngOnInit() {
    this.currentUser = this.authService.getUser();
    await this.loadUsers();
  }

  async loadUsers(page = 1) {
    this.isLoading = true;
    this.error = '';
    this.currentPage = page;
    try {
      this.loadingService.show();
      const res: any = await firstValueFrom(this.adminService.getUsers(page, this.pageSize));
      this.users = res.users || [];
      this.totalUsers = res.pagination?.total || 0;
      this.totalPages = res.pagination?.totalPages || 1;
      this.applyFilter();
    } catch (err: any) {
      this.error = err.error?.error || 'Error al cargar usuarios';
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilter() {
    if (!this.searchTerm.trim()) {
      this.filteredUsers = this.users;
      return;
    }
    const term = this.searchTerm.toLowerCase();
    this.filteredUsers = this.users.filter(u =>
      u.firstName?.toLowerCase().includes(term) ||
      u.lastName?.toLowerCase().includes(term) ||
      u.email?.toLowerCase().includes(term) ||
      u.documentNumber?.toLowerCase().includes(term)
    );
  }

  getUserInitials(user: any): string {
    return ((user.firstName?.charAt(0) || '') + (user.lastName?.charAt(0) || '')).toUpperCase() || '?';
  }

  openRoleEditor(user: any) {
    this.editRolesUser = user;
    this.selectedRoles = [...(user.roles || [])];
    this.isRoleModalOpen = true;
  }

  closeRoleModal() {
    this.isRoleModalOpen = false;
    this.editRolesUser = null;
    this.selectedRoles = [];
  }

  toggleRole(role: string) {
    const idx = this.selectedRoles.indexOf(role);
    if (idx >= 0) {
      this.selectedRoles.splice(idx, 1);
    } else {
      this.selectedRoles.push(role);
    }
  }

  isRoleSelected(role: string): boolean {
    return this.selectedRoles.includes(role);
  }

  async saveRoles() {
    if (!this.editRolesUser) return;
    if (this.selectedRoles.length === 0) {
      this.showToast('Debe seleccionar al menos un rol', 'danger');
      return;
    }
    try {
      this.loadingService.show();
      await firstValueFrom(this.adminService.updateUserRoles(this.editRolesUser.id, this.selectedRoles));
      this.editRolesUser.roles = [...this.selectedRoles];
      this.showToast('Roles actualizados correctamente', 'success');
      this.closeRoleModal();
    } catch (err: any) {
      this.showToast(err.error?.error || 'Error al actualizar roles', 'danger');
    } finally {
      this.loadingService.hide();
    }
  }

  isCurrentUser(user: any): boolean {
    return this.currentUser?.id === user.id;
  }

  get canDeleteUsers(): boolean {
    return this.authService.hasAnyRole('admin');
  }

  async deleteUser(user: any) {
    const alert = await this.alertCtrl.create({
      header: 'Eliminar usuario',
      message: `¿Seguro que deseas eliminar a <strong>${user.firstName} ${user.lastName}</strong>?<br>Esta acción borrará su cuenta, trámites y citas asociadas. Esta operación no se puede deshacer.`,
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Eliminar',
          role: 'destructive',
          handler: async () => {
            try {
              this.loadingService.show();
              await firstValueFrom(this.adminService.deleteUser(user.id));
              this.showToast('Usuario eliminado correctamente', 'success');
              await this.loadUsers(this.currentPage);
            } catch (err: any) {
              this.showToast(err.error?.error || 'Error al eliminar el usuario', 'danger');
            } finally {
              this.loadingService.hide();
            }
          },
        },
      ],
    });
    await alert.present();
  }

  private async showToast(message: string, color: 'success' | 'danger') {
    const toast = await this.toastCtrl.create({
      message,
      duration: 3000,
      color,
      position: 'bottom',
    });
    await toast.present();
  }
}
