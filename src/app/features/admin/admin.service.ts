import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { ApiConfigService } from '../../services/api-config.service';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/admin`;
  }

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  getDashboardStats() {
    return this.http.get<any>(`${this.apiUrl}/dashboard`);
  }

  getUsers(page = 1, limit = 20) {
    const params = new HttpParams().set('page', page).set('limit', limit);
    return this.http.get<any>(`${this.apiConfig.getApiUrl()}/auth/users`, { params });
  }

  updateUserRoles(userId: number, roles: string[]) {
    return this.http.put<any>(`${this.apiConfig.getApiUrl()}/auth/users/${userId}/roles`, { roles });
  }

  deleteUser(userId: number) {
    return this.http.delete<any>(`${this.apiConfig.getApiUrl()}/auth/users/${userId}`);
  }

  getDocuments(paramsData: {
    page?: number; limit?: number; search?: string; status?: string;
    documentType?: string; dateFrom?: string; dateTo?: string; userId?: number;
  }) {
    let params = new HttpParams();
    if (paramsData.page) params = params.set('page', paramsData.page);
    if (paramsData.limit) params = params.set('limit', paramsData.limit);
    if (paramsData.search) params = params.set('search', paramsData.search);
    if (paramsData.status) params = params.set('status', paramsData.status);
    if (paramsData.documentType) params = params.set('documentType', paramsData.documentType);
    if (paramsData.dateFrom) params = params.set('dateFrom', paramsData.dateFrom);
    if (paramsData.dateTo) params = params.set('dateTo', paramsData.dateTo);
    if (paramsData.userId) params = params.set('userId', paramsData.userId);
    return this.http.get<any>(`${this.apiUrl}/documents`, { params });
  }

  validateDocument(id: number, isValid: boolean) {
    return this.http.patch<any>(`${this.apiConfig.getApiUrl()}/documents/${id}/validate`, { isValid });
  }

  getDocumentDownloadUrl(id: number): string {
    return `${this.apiConfig.getApiUrl()}/documents/${id}/download`;
  }

  getAllRequests(paramsData: {
    page?: number; limit?: number; status?: string; search?: string;
    priority?: string; dateFrom?: string; dateTo?: string;
  }) {
    let params = new HttpParams();
    if (paramsData.page) params = params.set('page', paramsData.page);
    if (paramsData.limit) params = params.set('limit', paramsData.limit);
    if (paramsData.status) params = params.set('status', paramsData.status);
    if (paramsData.search) params = params.set('search', paramsData.search);
    if (paramsData.priority) params = params.set('priority', paramsData.priority);
    if (paramsData.dateFrom) params = params.set('dateFrom', paramsData.dateFrom);
    if (paramsData.dateTo) params = params.set('dateTo', paramsData.dateTo);
    return this.http.get<any>(`${this.apiConfig.getApiUrl()}/requests/all`, { params });
  }

  updateRequestStatus(id: number, status: string, comment?: string, internalNotes?: string) {
    return this.http.patch<any>(`${this.apiConfig.getApiUrl()}/requests/${id}/status`, { status, comment, internalNotes });
  }

  assignRequest(id: number, assignedTo: number | null) {
    return this.http.patch<any>(`${this.apiConfig.getApiUrl()}/requests/${id}/assign`, { assignedTo });
  }

  getAllAppointments(paramsData: { date?: string; status?: string }) {
    let params = new HttpParams();
    if (paramsData.date) params = params.set('date', paramsData.date);
    if (paramsData.status) params = params.set('status', paramsData.status);
    return this.http.get<any>(`${this.apiConfig.getApiUrl()}/appointments/all`, { params });
  }

  cancelAppointment(id: number) {
    return this.http.patch<any>(`${this.apiConfig.getApiUrl()}/appointments/${id}/cancel`, {});
  }

  getFuncionarios() {
    return this.http.get<any>(`${this.apiUrl}/funcionarios`);
  }

  getPopulationMap() {
    return this.http.get<any>(`${this.apiUrl}/population-map`);
  }
}
