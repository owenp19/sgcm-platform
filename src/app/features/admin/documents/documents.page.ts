import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AlertController } from '@ionic/angular';
import { AuthService } from '../../../services/auth.service';
import { AdminService } from '../admin.service';
import { LoadingService } from '../../../services/loading.service';
import { ApiConfigService } from '../../../services/api-config.service';

@Component({
  selector: 'app-documents',
  templateUrl: './documents.page.html',
  styleUrls: ['./documents.page.scss'],
  standalone: false,
})
export class DocumentsPage implements OnInit {
  documents: any[] = [];
  isLoading = true;
  error = '';
  searchTerm = '';
  statusFilter = '';
  currentPage = 1;
  totalPages = 1;
  totalDocs = 0;

  statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pending', label: 'Pendientes' },
    { value: 'validated', label: 'Validados' },
  ];

  constructor(
    private readonly adminService: AdminService,
    public readonly authService: AuthService,
    private readonly loadingService: LoadingService,
    private readonly alertCtrl: AlertController,
    private readonly apiConfig: ApiConfigService,
  ) {}

  async ngOnInit() {
    await this.loadDocuments();
  }

  async loadDocuments(page = 1) {
    this.isLoading = true;
    this.error = '';
    this.currentPage = page;
    try {
      this.loadingService.show();
      const res: any = await firstValueFrom(this.adminService.getDocuments({
        page, limit: 20, search: this.searchTerm || undefined,
        status: this.statusFilter || undefined,
      }));
      this.documents = res.documents || [];
      this.totalDocs = res.pagination?.total || 0;
      this.totalPages = res.pagination?.totalPages || 1;
    } catch (err: any) {
      this.error = err.error?.error || 'Error al cargar documentos';
    } finally {
      this.isLoading = false;
      this.loadingService.hide();
    }
  }

  applyFilter() {
    this.loadDocuments(1);
  }

  getDocumentUrl(doc: any): string {
    const token = this.authService.getToken();
    return `${this.apiConfig.getApiUrl()}/documents/${doc.id}/download?token=${token}`;
  }

  openDocument(doc: any) {
    window.open(this.getDocumentUrl(doc), '_blank');
  }

  async validateDocument(doc: any, isValid: boolean) {
    try {
      this.loadingService.show();
      await firstValueFrom(this.adminService.validateDocument(doc.id, isValid));
      doc.isValidated = isValid;
    } catch (err: any) {
      const alert = await this.alertCtrl.create({
        header: 'Error',
        message: err.error?.error || 'Error al validar documento',
        buttons: ['OK'],
      });
      await alert.present();
    } finally {
      this.loadingService.hide();
    }
  }

  async confirmValidate(doc: any, isValid: boolean) {
    const action = isValid ? 'aprobar' : 'rechazar';
    const alert = await this.alertCtrl.create({
      header: `¿${action === 'aprobar' ? 'Aprobar' : 'Rechazar'} documento?`,
      message: `¿Estás seguro de ${action} el documento "${doc.name}"?`,
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        { text: action === 'aprobar' ? 'Aprobar' : 'Rechazar', handler: () => this.validateDocument(doc, isValid) },
      ],
    });
    await alert.present();
  }

  formatFileSize(bytes: number): string {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }
}
