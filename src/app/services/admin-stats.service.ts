import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { ApiConfigService } from './api-config.service';

export interface DashboardAlerts {
  type: 'warning' | 'danger' | 'info' | 'success';
  title: string;
  message: string;
  count: number;
  link: string;
}

export interface StatItem {
  label: string;
  value: number;
}

export interface DashboardData {
  requests: {
    total: number; pendientes: number; en_revision: number; aprobados: number;
    rechazados: number; devueltos: number; finalizados: number; activos: number; resueltos: number;
  };
  users: { total: number; ciudadanos: number; funcionarios: number; admins: number };
  documents: { total: number; validados: number; pendientes: number };
  appointments: { total: number; pendientes: number; confirmadas: number; completadas: number; canceladas: number };
  recentRequests: Array<{
    id: number; trackingNumber: string; status: string; priority: string;
    procedureName: string; citizenName: string; submittedAt: string;
  }>;
  recentActivity: Array<{
    type: string; id: number; code: string; status: string | null;
    action: string | null; userName: string; createdAt: string;
  }>;
  monthlyStats: Array<{ month: string; total: number }>;
  topProcedures: Array<{ name: string; count: number }>;
  documentsByMonth: Array<{ month: string; total: number; validados: number; pendientes: number }>;
  usersByMonth: Array<{ month: string; total: number; ciudadanos: number; funcionarios: number }>;
  appointmentsByDay: Array<{ date: string; total: number; ocupados: number; cancelados: number; completados: number }>;
  requestsByStatus: Array<{ status: string; count: number; label: string }>;
  documentsByStatus: Array<{ status: string; count: number; label: string }>;
  alerts: DashboardAlerts[];
  lastUpdate: string;
  activeFuncionarios: number;
}

@Injectable({ providedIn: 'root' })
export class AdminStatsService {
  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/admin`;
  }

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  getDashboard(dateFrom?: string, dateTo?: string, status?: string) {
    let params = new HttpParams();
    if (dateFrom) params = params.set('dateFrom', dateFrom);
    if (dateTo) params = params.set('dateTo', dateTo);
    if (status) params = params.set('status', status);
    return this.http.get<DashboardData>(`${this.apiUrl}/dashboard`, { params });
  }

  getReports(params: { dateFrom?: string; dateTo?: string; groupBy?: string }) {
    let p = new HttpParams();
    if (params.dateFrom) p = p.set('dateFrom', params.dateFrom);
    if (params.dateTo) p = p.set('dateTo', params.dateTo);
    if (params.groupBy) p = p.set('groupBy', params.groupBy);
    return this.http.get<any>(`${this.apiUrl}/reports`, { params: p });
  }

  getExportExcelUrl(params: { dateFrom?: string; dateTo?: string; status?: string }): string {
    const base = `${this.apiUrl}/export/excel`;
    const search = new URLSearchParams();
    if (params.dateFrom) search.set('dateFrom', params.dateFrom);
    if (params.dateTo) search.set('dateTo', params.dateTo);
    if (params.status) search.set('status', params.status);
    const qs = search.toString();
    return qs ? `${base}?${qs}` : base;
  }

  getExportPdfUrl(params: { dateFrom?: string; dateTo?: string; status?: string }): string {
    const base = `${this.apiUrl}/export/pdf`;
    const search = new URLSearchParams();
    if (params.dateFrom) search.set('dateFrom', params.dateFrom);
    if (params.dateTo) search.set('dateTo', params.dateTo);
    if (params.status) search.set('status', params.status);
    const qs = search.toString();
    return qs ? `${base}?${qs}` : qs;
  }

  getFuncionarios() {
    return this.http.get<any>(`${this.apiUrl}/funcionarios`);
  }
}
