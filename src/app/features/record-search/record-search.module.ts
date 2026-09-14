import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { FormsModule } from '@angular/forms';
import { RecordSearchPageRoutingModule } from './record-search-routing.module';
import { RecordSearchPage } from './record-search.page';
import { SharedModule } from '../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    FormsModule,
    RecordSearchPageRoutingModule,
    SharedModule,
  ],
  declarations: [RecordSearchPage],
})
export class RecordSearchPageModule {}
