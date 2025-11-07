import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document, SchemaTypes } from 'mongoose';

@Schema({ timestamps: true })
export class Listing extends Document {
  @Prop({ required: true })
  title: string;

  @Prop({ required: true })
  description: string;

  @Prop({ required: true })
  price: number;

  @Prop({ type: SchemaTypes.ObjectId, ref: 'User', required: true })
  owner: string;

  @Prop({ required: true })
  location: string;

  @Prop({ required: true })
  area: number;

  @Prop({ type: [String], default: [] })
  images: string[];

  @Prop({ default: true })
  isAvailable: boolean;

  @Prop({ type: Object })
  metadata: Record<string, any>;
}

export const ListingSchema = SchemaFactory.createForClass(Listing);
