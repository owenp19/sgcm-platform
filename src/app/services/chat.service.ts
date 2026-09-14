import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ApiConfigService } from './api-config.service';

export interface ChatHistoryItem {
  role: 'user' | 'model';
  text: string;
}

interface ChatResponse {
  answer: string;
}

@Injectable({ providedIn: 'root' })
export class ChatService {
  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/chat`;
  }

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  ask(
    message: string,
    history: ChatHistoryItem[],
    context?: string
  ): Promise<string> {
    const headers = new HttpHeaders({ 'X-Skip-Error-Toast': 'true' });

    return firstValueFrom(
      this.http.post<ChatResponse>(
        this.apiUrl,
        {
          message,
          history,
          ...(context ? { context } : {}),
        },
        { headers }
      )
    ).then((res) => res.answer);
  }
}