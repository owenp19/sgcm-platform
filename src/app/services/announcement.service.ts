import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiConfigService } from './api-config.service';

export interface Announcement {
  id: number;
  title: string;
  body: string;
  type: 'info' | 'alert' | 'important';
  isActive?: boolean;
  publishedAt: string;
}

@Injectable({ providedIn: 'root' })
export class AnnouncementService {
  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/announcements`;
  }

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  getActive() {
    return this.http.get<{ announcements: Announcement[] }>(`${this.apiUrl}/active`);
  }

  getAll() {
    return this.http.get<{ announcements: Announcement[] }>(this.apiUrl);
  }

  create(data: { title: string; body: string; type: string }) {
    return this.http.post<{ message: string; id: number }>(this.apiUrl, data);
  }

  update(id: number, data: { title?: string; body?: string; type?: string; isActive?: boolean }) {
    return this.http.put<{ message: string }>(`${this.apiUrl}/${id}`, data);
  }

  delete(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${id}`);
  }
}
