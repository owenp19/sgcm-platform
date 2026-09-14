import { Pipe, PipeTransform, OnDestroy } from '@angular/core';
import { TranslationService } from '../services/translation.service';

@Pipe({
  name: 'translate',
  pure: false,
  standalone: false,
})
export class TranslatePipe implements PipeTransform, OnDestroy {
  private lastKey = '';
  private lastValue = '';
  private unsubscribe: () => void;

  constructor(private readonly service: TranslationService) {
    this.unsubscribe = this.service.onChange(() => {
      this.lastValue = this.service.translate(this.lastKey);
    });
  }

  transform(key: string, params?: Record<string, string | number>): string {
    if (key !== this.lastKey || params) {
      this.lastKey = key;
      this.lastValue = this.service.translate(key);
      if (params) {
        for (const [k, v] of Object.entries(params)) {
          this.lastValue = this.lastValue.replace(`{${k}}`, String(v));
        }
      }
    }
    return this.lastValue;
  }

  ngOnDestroy(): void {
    this.unsubscribe();
  }
}
